<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sede;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Gasto;
use App\Models\Compra;
use App\Models\TransferenciaSede;
use App\Models\SolicitudExoneracion;
use App\Models\SolicitudResolucionExcedente;
use App\Models\FondoSede;
use App\Models\AperturaCierreDia;
use App\Models\PromotorCobrador;
use App\Services\CarteraReportService;
use App\Services\SedeAccessService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteExportController extends Controller
{
    private function authorizeGerenciaOrPermission(Request $request, string $permission): void
    {
        abort_unless(
            $request->user()?->puedeAccederAGerencia()
                || $request->user()?->can($permission),
            403
        );
    }

    private function resolveSedeId(): ?int
    {
        $user = auth()->user();
        return app(SedeAccessService::class)
            ->resolveReportSedeId($user, request()->get('sede_id'));
    }

    private function getStyles(): array
    {
        return [
            'title' => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
            'header' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            'total' => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F0FE']],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            'data' => [
                'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
        ];
    }

    private function writeDataRow($sheet, $row, array $data, int $cols): void
    {
        for ($col = 0; $col < $cols; $col++) {
            $colLetter = chr(65 + $col);
            $sheet->setCellValue($colLetter . $row, $data[$col] ?? '');
            $sheet->getStyle($colLetter . $row)->applyFromArray($this->getStyles()['data']);
        }
    }

    // ─── BALANCE DIARIO ───
    public function diarioExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'balance_diario');

        $fecha = $request->get('fecha');
        if (!$fecha) { abort(400, 'Fecha no proporcionada'); }
        $sedeId = $this->resolveSedeId();
        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $fechaInicioDia = $fechaCarbon->copy()->startOfDay();
        $fechaFinDia = $fechaCarbon->copy()->endOfDay();
        $sedeNombre = $sedeId ? (Sede::find($sedeId)?->Nombre ?? 'SEDE NO ESPECIFICADA') : 'TODAS LAS SEDES';

        // ─── Datos ───
        $gastos = Gasto::withoutGlobalScopes()->where('Activo', true)->whereDate('FechaEmision', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with('proveedor', 'motivo')->orderBy('GastoID')->get();
        $compras = Compra::withoutGlobalScopes()->where('Activo', true)->whereDate('FechaEmision', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with('proveedor')->orderBy('CompraID')->get();

        $exoneraciones = \App\Models\SolicitudExoneracion::withoutGlobalScopes()
            ->where('Estado', 'APROBADO')->where('Activo', true)
            ->whereBetween('FechaAprobacion', [$fechaInicioDia, $fechaFinDia])
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with(['credito.proposicion.cliente', 'tipoExoneracion'])->orderBy('SolicitudExoneracionID')->get();

        $extornos = \App\Models\SolicitudResolucionExcedente::withoutGlobalScopes()
            ->where('Estado', 'APROBADA')->whereBetween('created_at', [$fechaInicioDia, $fechaFinDia])
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with(['clienteOrigen', 'clienteDestino', 'excedente', 'creditoDestino.proposicion.cliente', 'creditoOrigen.proposicion.cliente'])
            ->orderBy('SolicitudID')->get();

        $pagos = \App\Models\Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)->where('pago.EsPagoAMayorPorMora', false)
            ->where('pago.ExcluirBalanceDiario', false)
            ->whereNull('pago.SolicitudResolucionID')
            ->whereDate('pago.FechaPago', $fecha)->when($sedeId, fn($q) => $q->where('pago.SedeID', $sedeId))
            ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select('pago.PagoID', 'pago.MontoPagado', 'pago.Comentario', 'pago.TipoPago', 'ProposicionCredito.CodigoCredito', 'Cliente.NombresApellidos', 'Zona.Nombre as ZonaNombre', 'TipoCredito.Codigo as TipoCreditoCodigo')
            ->orderBy('pago.PagoID')->get()
            ->each(function($p) {
                if ($p->Comentario && preg_match('/Pago original:\s*S\/\s*([\d,]+(?:\.\d{2})?)/', $p->Comentario, $m)) {
                    $p->MontoOriginal = (float) str_replace(',', '', $m[1]);
                } else {
                    $p->MontoOriginal = (float)$p->MontoPagado;
                }
            });
        $totalAmortizaciones = $pagos->sum('MontoOriginal');

        $moras = \App\Models\Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)->where('pago.EsPagoAMayorPorMora', true)
            ->whereDate('pago.FechaPago', $fecha)->when($sedeId, fn($q) => $q->where('pago.SedeID', $sedeId))
            ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->select('ProposicionCredito.CodigoCredito', 'Cliente.NombresApellidos', 'pago.MontoPagado')
            ->orderBy('pago.PagoID')->get();
        $totalMoras = $moras->sum('MontoPagado');

        $creditos = Credito::withoutGlobalScopes()->where('Credito.Activo', true)->whereDate('Credito.FechaGeneracion', $fecha)
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->select('ProposicionCredito.CodigoCredito', 'Cliente.NombresApellidos', 'ProposicionCredito.MontoTotal', 'ProposicionCredito.MontoInteres', 'ProposicionCredito.MontoTotalPagar')
            ->orderBy('Credito.CreditoID')->get();
        $totalCreditosEmitidos = $creditos->sum('MontoTotal');

        $excedentesDia = \App\Models\Excedente::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)
            ->where(function($q) { $q->where('Cuenta', 'Caja Abierta')->orWhereNull('Cuenta'); })
            ->whereBetween('Fecha', [$fechaInicioDia, $fechaFinDia])
            ->with(['resoluciones' => fn($q) => $q->withoutGlobalScopes()->where('Estado', 'APROBADA')->with('creditoDestino.proposicion.cliente')])
            ->withSum(['resoluciones as monto_aplicado' => function($q) { $q->withoutGlobalScopes()->where('Estado', 'APROBADA'); }], 'MontoAplicar')
            ->orderBy('ExcedenteID')->get();
        $totalExcedentesDia = $excedentesDia->sum(fn($e) => (float)$e->Monto + (float)($e->monto_aplicado ?? 0));

        $ingresosRemesas = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]));
            })
            ->when($sedeId, fn($q) => $q->where(function ($q2) use ($sedeId) {
                $q2->where('EsSolicitudGerencia', true)->where('SedeOrigenID', $sedeId)
                   ->orWhere(fn($q3) => $q3->where(fn($q4) => $q4->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeDestinoID', $sedeId));
            }))
            ->with('sedeOrigen', 'sedeDestino')->orderBy('TransferenciaID')->get();

        $salidasRemesas = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]));
            })
            ->when($sedeId, fn($q) => $q->where(function ($q2) use ($sedeId) {
                $q2->where('EsSolicitudGerencia', true)->where('SedeDestinoID', $sedeId)
                   ->orWhere(fn($q3) => $q3->where(fn($q4) => $q4->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeOrigenID', $sedeId));
            }))
            ->with('sedeOrigen', 'sedeDestino')->orderBy('TransferenciaID')->get();

        $devolucionesDia = \App\Models\MovimientoFondo::withoutGlobalScopes()
            ->where('SedeID', $sedeId)->where('Tipo', 'EGRESO_DEVOLUCION_EFECTIVO')
            ->whereBetween('FechaMovimiento', [$fechaInicioDia, $fechaFinDia])->sum(\DB::raw('ABS(Monto)'));

        // Remesas netas CA (con compensacion EsSolicitudGerencia)
        $ingRemCA = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')->where('CuentaDestino', 'CAJA_ABIERTA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->where(fn($q) => $q->where('EsSolicitudGerencia', true)->where('SedeOrigenID', $sedeId)
                ->orWhere(fn($q2) => $q2->where(fn($q3) => $q3->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeDestinoID', $sedeId)))
            ->sum('Monto');
        $salRemCA = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_ABIERTA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->where(fn($q) => $q->where('EsSolicitudGerencia', true)->where('SedeDestinoID', $sedeId)
                ->orWhere(fn($q2) => $q2->where(fn($q3) => $q3->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeOrigenID', $sedeId)))
            ->sum('Monto');
        $remesasNetCajaAbierta = $ingRemCA - $salRemCA;

        $ingRemCC = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')->where('CuentaDestino', 'CAJA_CHICA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->where(fn($q) => $q->where('EsSolicitudGerencia', true)->where('SedeOrigenID', $sedeId)
                ->orWhere(fn($q2) => $q2->where(fn($q3) => $q3->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeDestinoID', $sedeId)))
            ->sum('Monto');
        $salRemCC = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_CHICA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->where(fn($q) => $q->where('EsSolicitudGerencia', true)->where('SedeDestinoID', $sedeId)
                ->orWhere(fn($q2) => $q2->where(fn($q3) => $q3->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia'))->where('SedeOrigenID', $sedeId)))
            ->sum('Monto');
        $remesasNetCajaChica = $ingRemCC - $salRemCC;

        // Saldos Caja Chica
        $saldoInicialCajaChica = 0; $totalGastosCC = $gastos->sum('Total') + $compras->sum('Total');
        if ($sedeId) {
            $ingCC = \App\Models\MovimientoFondo::withoutGlobalScopes()->where('SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where(fn($q) => $q->where('Tipo', 'INGRESO_CAJA_CHICA')->orWhere('Tipo', 'TRASLADO_CA_A_CC'))
                ->where('Observacion', 'NOT LIKE', '%Ajuste%')->where('Observacion', 'NOT LIKE', '%Reversión%')
                ->get()->sum(fn($m) => $m->Tipo === 'TRASLADO_CA_A_CC' ? abs($m->Monto) : $m->Monto);
            $ingCC += \App\Models\MovimientoFondo::withoutGlobalScopes()->where('movimientos_fondo.SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where('movimientos_fondo.Tipo', 'RECEPCION_TRANSFERENCIA')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaDestino', 'CAJA_CHICA')->sum('movimientos_fondo.Monto');
            $dedCC = Gasto::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('MetodoGasto', 'CAJA CHICA')->whereDate('FechaEmision', '<', $fecha)->sum('Total');
            $dedCC += Compra::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('TipoCompra', 'CONTADO')->whereDate('FechaEmision', '<', $fecha)->sum('Total');
            $dedCC += \App\Models\MovimientoFondo::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Tipo', 'TRASLADO_CC_A_CA')->where('FechaMovimiento', '<', $fechaInicioDia)->sum(\DB::raw('ABS(Monto)'));
            $dedCC += \App\Models\MovimientoFondo::withoutGlobalScopes()->where('movimientos_fondo.SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where('movimientos_fondo.Tipo', 'ENVIO_TRANSFERENCIA')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaOrigen', 'CAJA_CHICA')->sum(\DB::raw('ABS(movimientos_fondo.Monto)'));
            $saldoInicialCajaChica = $ingCC - $dedCC;
        }
        $totalCajaChica = $saldoInicialCajaChica - $totalGastosCC + $remesasNetCajaChica;

        // Saldo Inicial Caja Abierta
        $saldoInicialCajaAbierta = 0;
        if ($sedeId) {
            $calcularSaldo = function($limite) use ($sedeId) {
                $tr = TransferenciaSede::withoutGlobalScopes()->where('SedeDestinoID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaDestino', 'CAJA_ABIERTA')
                    ->where(function($q) use ($limite) {
                        $q->where('FechaRespuesta', '<=', $limite)->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $limite));
                    })
                    ->where(function($q) use ($sedeId) {
                        $q->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia')
                          ->orWhere(function($q2) use ($sedeId) {
                              $q2->where('EsSolicitudGerencia', true)->where('SedeOrigenID', $sedeId);
                          });
                    })
                    ->sum('Monto');
                $te = TransferenciaSede::withoutGlobalScopes()->where('SedeOrigenID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_ABIERTA')
                    ->where(function($q) use ($limite) {
                        $q->where('FechaRespuesta', '<=', $limite)->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $limite));
                    })
                    ->where(function($q) use ($sedeId) {
                        $q->where('EsSolicitudGerencia', false)->orWhereNull('EsSolicitudGerencia')
                          ->orWhere(function($q2) use ($sedeId) {
                              $q2->where('EsSolicitudGerencia', true)->where('SedeDestinoID', $sedeId);
                          });
                    })
                    ->sum('Monto');
                $pg = \App\Models\Pago::withoutGlobalScopes()->where('Activo', true)->where('EsPagoAMayorPorMora', false)
                    ->where('ExcluirBalanceDiario', false)
                    ->whereNull('SolicitudResolucionID')
                    ->where(function($q) {
                        $q->whereNull('TipoConcepto')
                          ->orWhere('TipoConcepto', 'C');
                    })
                    ->where('FechaPago', '<=', $limite)->where('SedeID', $sedeId)
                    ->select('MontoPagado', 'Comentario')
                    ->get()
                    ->sum(function ($p) {
                        if ($p->Comentario && preg_match('/Pago original:\s*S\/\s*([\d,]+(?:\.\d{2})?)/', $p->Comentario, $m)) {
                            return (float) str_replace(',', '', $m[1]);
                        }

                        return (float) $p->MontoPagado;
                    });
                $cr = Credito::withoutGlobalScopes()->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
                    ->where('Credito.Activo', true)->where('Credito.SedeID', $sedeId)->where('Credito.FechaGeneracion', '<=', $limite)->sum('ProposicionCredito.MontoTotal');
                $ot = \App\Models\MovimientoFondo::withoutGlobalScopes()->where('SedeID', $sedeId)->where('FechaMovimiento', '<=', $limite)
                    ->whereIn('Tipo', ['INGRESO_CAPITAL', 'TRASLADO_CA_A_CC', 'TRASLADO_CC_A_CA', 'EGRESO_DEVOLUCION_EFECTIVO'])->get();
                $in = $ot->where('Tipo', 'INGRESO_CAPITAL')->sum('Monto');
                $teCc = $ot->where('Tipo', 'TRASLADO_CC_A_CA')->sum(fn($m) => abs($m->Monto));
                $tsCc = $ot->where('Tipo', 'TRASLADO_CA_A_CC')->sum(fn($m) => abs($m->Monto));
                $devEf = $ot->where('Tipo', 'EGRESO_DEVOLUCION_EFECTIVO')->sum(fn($m) => abs($m->Monto));
                $ex = \App\Models\Excedente::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)
                    ->where(fn($q) => $q->where('Cuenta', 'Caja Abierta')->orWhereNull('Cuenta'))->where('Fecha', '<=', $limite)
                    ->withSum(['resoluciones as ma' => fn($q) => $q->withoutGlobalScopes()->where('Estado', 'APROBADA')], 'MontoAplicar')->get()
                    ->sum(fn($e) => (float)$e->Monto + (float)($e->ma ?? 0));
                $mo = \App\Models\Pago::withoutGlobalScopes()->where('Activo', true)->where('EsPagoAMayorPorMora', true)
                    ->where('FechaPago', '<=', $limite)->where('SedeID', $sedeId)->sum('MontoPagado');
                return $tr + $pg + $in + $teCc + $ex + $mo - $te - $cr - $tsCc - $devEf;
            };
            $saldoInicialCajaAbierta = $calcularSaldo($fechaInicioDia->copy()->subSecond());
        }
        $totalCajaAbierta = $saldoInicialCajaAbierta + $totalAmortizaciones + $totalMoras - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;
        $saldoInicialReal = $saldoInicialCajaAbierta - 150000;
        $totalCajaAbiertaReal = $saldoInicialReal + $totalAmortizaciones + $totalMoras - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;
        $saldoCuentaAMayor = \App\Models\Pago::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('EsPagoAMayor', true)
            ->whereNull('SolicitudResolucionID')->where('FechaPago', '<=', $fechaFinDia)->sum('MontoPagado');

        // ─── Excel ───
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(); $sheet->setTitle('Balance Diario');
        $styles = $this->getStyles(); $row = 1;
        $sheet->mergeCells('A1:F1'); $sheet->setCellValue('A1', "BALANCE DIARIO - {$fecha}"); $sheet->getStyle('A1')->applyFromArray($styles['title']); $sheet->getRowDimension(1)->setRowHeight(25);
        $row = 2; $sheet->setCellValue('A'.$row, "Sede: {$sedeNombre}"); $row += 2;

        // 1. PAGOS REALIZADOS
        $sheet->setCellValue('A'.$row, 'PAGOS REALIZADOS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Operación','Zona','Cred.','Cliente','Tipo','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        foreach ($pagos as $p) {
            $tipo = match($p->TipoPago ?? '') { 'EFECTIVO'=>'Efectivo', 'YAPE_PLIN'=>'Yape/Plin', 'TRANSFERENCIA','TRANSFERENCIA_BANCARIA'=>'Transferencia', default=>$p->TipoPago??'-' };
            $this->writeDataRow($sheet, $row, [$p->CodigoCredito, $p->ZonaNombre, $p->TipoCreditoCodigo?:'001', $p->NombresApellidos, $tipo, $p->MontoOriginal], 6);
            $sheet->getStyle('F'.$row)->getAlignment()->setHorizontal('right'); $row++;
        }
        $sheet->setCellValue('E'.$row, 'TOTAL DE PAGOS:'); $sheet->setCellValue('F'.$row, $totalAmortizaciones);
        $sheet->getStyle('E'.$row.':F'.$row)->applyFromArray($styles['total']); $row += 2;

        // 2. MORAS
        $sheet->setCellValue('A'.$row, 'MORAS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Operación','Cliente','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        foreach ($moras as $m) { $this->writeDataRow($sheet, $row, [$m->CodigoCredito, $m->NombresApellidos, $m->MontoPagado], 3); $sheet->getStyle('C'.$row)->getAlignment()->setHorizontal('right'); $row++; }
        $sheet->setCellValue('A'.$row, 'TOTAL MORAS:'); $sheet->setCellValue('C'.$row, $totalMoras);
        $sheet->getStyle('A'.$row.':C'.$row)->applyFromArray($styles['total']); $row += 2;

        // 3. CREDITOS EMITIDOS
        $sheet->setCellValue('A'.$row, 'CREDITOS EMITIDOS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Operación','Cliente','Capital','Interés','Total'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tCap=$tInt=$tPag=0;
        foreach ($creditos as $c) { $this->writeDataRow($sheet, $row, [$c->CodigoCredito, $c->NombresApellidos, $c->MontoTotal, $c->MontoInteres, $c->MontoTotalPagar], 5); foreach(['C','D','E'] as $col) $sheet->getStyle($col.$row)->getAlignment()->setHorizontal('right'); $tCap+=$c->MontoTotal; $tInt+=$c->MontoInteres; $tPag+=$c->MontoTotalPagar; $row++; }
        $sheet->setCellValue('A'.$row, 'TOTAL CREDITOS:'); $sheet->setCellValue('C'.$row, $tCap); $sheet->setCellValue('D'.$row, $tInt); $sheet->setCellValue('E'.$row, $tPag);
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 4. EXCEDENTES
        $sheet->setCellValue('A'.$row, 'EXCEDENTES'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Nro. Op.','Fecha','Tipo','Regularizado A','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        foreach ($excedentesDia as $exc) {
            $montoO = (float)$exc->Monto + (float)($exc->monto_aplicado ?? 0);
            $reg = ''; $res = $exc->resoluciones->first();
            if ($res && $res->creditoDestino && $res->creditoDestino->proposicion) $reg = $res->creditoDestino->proposicion->CodigoCredito.' - '.$res->creditoDestino->proposicion->cliente->NombresApellidos;
            $tipoE = match($exc->TipoExcedente){'YAPE_TRANSFERENCIA'=>'Yape/Transfer.','SOBRANTE_PROMOTOR'=>'Sobr. Promotor','SOBRANTE_CAJERO'=>'Exced. Oficina',default=>$exc->TipoExcedente};
            $this->writeDataRow($sheet, $row, [$exc->NroOperacion?:'-', $exc->Fecha->format('d/m/Y'), $tipoE, $reg?:'—', $montoO], 5);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $row++;
        }
        $sheet->setCellValue('D'.$row, 'TOTAL EXCEDENTES:'); $sheet->setCellValue('E'.$row, $totalExcedentesDia);
        $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 5. INGRESO DE REMESAS
        $sheet->setCellValue('A'.$row, 'INGRESO DE REMESAS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Nro.','Fecha','Sede Origen','Cuenta','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tIng=0; foreach ($ingresosRemesas as $r) { $this->writeDataRow($sheet, $row, [$r->TransferenciaID, ($r->FechaRespuesta??$r->FechaTransferencia)?->format('d/m/Y'), $r->EsSolicitudGerencia ? ($r->sedeDestino?->Nombre ?? '-') : ($r->sedeOrigen?->Nombre ?? '-'), $r->CuentaDestino?:'CAJA_ABIERTA', $r->Monto], 5); $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tIng+=$r->Monto; $row++; }
        $sheet->setCellValue('D'.$row, 'TOTAL INGRESOS:'); $sheet->setCellValue('E'.$row, $tIng); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 6. SALIDA DE REMESAS
        $sheet->setCellValue('A'.$row, 'SALIDA DE REMESAS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Nro.','Fecha','Sede Destino','Cuenta','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tSal=0; foreach ($salidasRemesas as $r) { $this->writeDataRow($sheet, $row, [$r->TransferenciaID, ($r->FechaRespuesta??$r->FechaTransferencia)?->format('d/m/Y'), $r->EsSolicitudGerencia ? ($r->sedeOrigen?->Nombre ?? '-') : ($r->sedeDestino?->Nombre ?? '-'), $r->CuentaOrigen?:'CAJA_ABIERTA', $r->Monto], 5); $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tSal+=$r->Monto; $row++; }
        $sheet->setCellValue('D'.$row, 'TOTAL SALIDAS:'); $sheet->setCellValue('E'.$row, $tSal); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 7. EXTORNOS Y DEVOLUCIONES
        $sheet->setCellValue('A'.$row, 'EXTORNOS Y DEVOLUCIONES'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Operación','Fecha','CTA Cliente','Tipo','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tExt=0;
        foreach ($extornos as $e) {
            $clienteNombre = $e->creditoDestino?->proposicion?->cliente?->NombresApellidos ?? $e->clienteDestino?->NombresApellidos ?? $e->clienteOrigen?->NombresApellidos ?? 'N/A';
            $codigoCredito = $e->creditoDestino?->proposicion?->CodigoCredito ?? '';
            $ctaCliente = $codigoCredito ? "{$codigoCredito} - ".mb_strtoupper($clienteNombre) : mb_strtoupper($clienteNombre);
            if (!in_array($e->TipoResolucion, ['TRASLADO_DE_PAGO', 'APLICACION_PAGO_MAYOR'], true)) $tExt += $e->MontoAplicar;
            $tipo = in_array($e->TipoResolucion, ['TRASLADO_DE_PAGO', 'APLICACION_PAGO_MAYOR'], true) ? 'TRAS' : 'EXT';
            $this->writeDataRow($sheet, $row, [$e->excedente?->NroOperacion??'', $e->created_at->format('d/m/Y'), $ctaCliente, $tipo, $e->MontoAplicar], 5);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $row++;
        }
        foreach ($exoneraciones as $e) {
            $clienteNombreExo = $e->credito?->proposicion?->cliente?->NombresApellidos ?? '';
            $codigoCreditoExo = $e->credito?->proposicion?->CodigoCredito ?? '';
            $ctaClienteExo = $codigoCreditoExo ? "{$codigoCreditoExo} - ".mb_strtoupper($clienteNombreExo) : mb_strtoupper($clienteNombreExo);
            $tExt += $e->MontoExonerado;
            $this->writeDataRow($sheet, $row, [$codigoCreditoExo, $e->FechaAprobacion?->format('d/m/Y'), $ctaClienteExo, 'EXO', $e->MontoExonerado], 5);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $row++;
        }
        $sheet->setCellValue('D'.$row, 'TOTAL EXT. Y DEV.:'); $sheet->setCellValue('E'.$row, $tExt);
        $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 8. GASTOS CAJA CHICA
        $sheet->setCellValue('A'.$row, 'GASTOS CAJA CHICA'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Nro.','Fecha','Proveedor / Motivo','Observación','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tCC=0;
        foreach ($gastos as $g) {
            $refG = $g->proveedor?->Nombre ?? ($g->proveedor?->RazonSocial ?? '') ?: ($g->motivo?->Descripcion ?? '') ?: 'Gasto #'.$g->GastoID;
            $this->writeDataRow($sheet, $row, ['G-'.$g->GastoID, $g->FechaEmision->format('d/m/Y'), $refG, $g->Observaciones??'', $g->Total], 5);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tCC+=$g->Total; $row++;
        }
        foreach ($compras as $c) {
            $refC = $c->proveedor?->Nombre ?? ($c->proveedor?->RazonSocial ?? '') ?: 'Compra #'.$c->CompraID;
            $this->writeDataRow($sheet, $row, ['C-'.$c->CompraID, $c->FechaEmision->format('d/m/Y'), $refC, $c->Observaciones??'', $c->Total], 5);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tCC+=$c->Total; $row++;
        }
        $sheet->setCellValue('D'.$row, 'TOTAL CAJA CHICA:'); $sheet->setCellValue('E'.$row, $tCC); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 9. BALANCE DE CAJA
        $sheet->setCellValue('A'.$row, 'BALANCE DE CAJA'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        $sheet->setCellValue('A'.$row, 'CAJA ABIERTA'); $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->setCellValue('D'.$row, 'CAJA CHICA'); $sheet->getStyle('D'.$row)->getFont()->setBold(true); $row++;
        foreach (['Concepto','Monto','','Concepto','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $itemsCA = [
            ['Saldo Inicial:', $saldoInicialReal],
            ['Pagos (+):', $totalAmortizaciones],
            ['Moras (+):', $totalMoras],
            ['Créditos (-):', -$totalCreditosEmitidos],
            ['Remesas (+/-):', $remesasNetCajaAbierta],
            ['Excedentes (+):', $totalExcedentesDia],
            ['Devoluciones (-):', -$devolucionesDia],
        ];
        $itemsCC = [
            ['Saldo Inicial:', $saldoInicialCajaChica],
            ['Gastos (-):', -$totalGastosCC],
            ['Remesas (+/-):', $remesasNetCajaChica],
        ];
        $maxR = max(count($itemsCA), count($itemsCC));
        for ($i = 0; $i < $maxR; $i++) {
            if (isset($itemsCA[$i])) { $sheet->setCellValue('A'.$row, $itemsCA[$i][0]); $sheet->setCellValue('B'.$row, $itemsCA[$i][1]); $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal('right'); }
            if (isset($itemsCC[$i])) { $sheet->setCellValue('D'.$row, $itemsCC[$i][0]); $sheet->setCellValue('E'.$row, $itemsCC[$i][1]); $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); }
            $row++;
        }
        $sheet->setCellValue('A'.$row, 'TOTAL:'); $sheet->setCellValue('B'.$row, $totalCajaAbiertaReal);
        $sheet->setCellValue('D'.$row, 'TOTAL:'); $sheet->setCellValue('E'.$row, $totalCajaChica);
        $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray($styles['total']); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']);
        $row++; $sheet->setCellValue('A'.$row, 'Cta. a Mayor:'); $sheet->setCellValue('B'.$row, $saldoCuentaAMayor); $row += 2;

        // Column widths
        foreach (range('A','F') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        return $this->downloadSpreadsheet($spreadsheet, "Balance_Diario_{$fecha}");
    }

    // ─── CUENTAS CANCELADAS ───
    public function canceladasExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'view_any_reporte::cuentas::canceladas');

        $fecha = $request->get('fecha') ? Carbon::createFromFormat('Y-m-d', $request->get('fecha')) : now();
        $sedeId = $this->resolveSedeId();

        $proposiciones = \App\Models\ProposicionCredito::withoutGlobalScopes()
            ->select('ProposicionCredito.*')
            ->join('Credito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('ProposicionCredito.SaldoPendiente', 0)
            ->whereDate('Credito.FechaSaldamiento', '=', $fecha)
            ->when($sedeId, fn($q) => $q->where('ProposicionCredito.SedeID', $sedeId))
            ->with(['cliente', 'credito', 'zona'])
            ->orderByDesc('Credito.FechaSaldamiento')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Canceladas');
        $styles = $this->getStyles();
        $row = 1;

        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', "CUENTAS CANCELADAS - {$fecha->format('d/m/Y')}");
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $headers = ['Operación', 'Cliente', 'Zona', 'Cuenta', 'Monto Entregado', 'Interés', 'Total', 'F. Entrega', 'F. Saldado', 'Vencimiento'];
        $colCount = count($headers);
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;

        $total = 0; $totalMonto = 0; $totalInteres = 0;
        foreach ($proposiciones as $prop) {
            $this->writeDataRow($sheet, $row, [
                str_pad($prop->ProposicionCreditoID, 11, '0', STR_PAD_LEFT),
                $prop->cliente?->NombresApellidos ?? '-',
                $prop->zona?->Nombre ?? '-',
                $prop->CodigoCredito,
                $prop->MontoTotal ?? 0,
                $prop->MontoInteres ?? 0,
                $prop->MontoTotalPagar,
                $prop->credito?->FechaGeneracion?->format('d/m/Y') ?? '-',
                $prop->credito?->FechaSaldamiento?->format('d/m/Y') ?? '-',
                $prop->credito?->FechaVencimiento?->format('d/m/Y') ?? '-',
            ], $colCount);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
            $total += $prop->MontoTotalPagar;
            $totalMonto += $prop->MontoTotal ?? 0;
            $totalInteres += $prop->MontoInteres ?? 0;
            $row++;
        }

        $sheet->setCellValue('D' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('E' . $row, $totalMonto);
        $sheet->setCellValue('F' . $row, $totalInteres);
        $sheet->setCellValue('G' . $row, $total);
        $sheet->getStyle('D' . $row . ':G' . $row)->applyFromArray($styles['total']);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(14);

        return $this->downloadSpreadsheet($spreadsheet, "Cuentas_Canceladas_{$fecha->format('d-m-Y')}");
    }

    // ─── REPORTE DE CARTERA ───
    public function carteraExcel(Request $request, CarteraReportService $carteraReportService)
    {
        $this->authorizeGerenciaOrPermission($request, 'reporte_cartera');
        $fecha = $request->get('fecha');
        $tipos = $request->get('tipos', '');
        if (!$fecha) {
            abort(400, 'Debe especificar una fecha.');
        }
        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $tiposArray = array_filter(explode(',', $tipos));
        if (empty($tiposArray)) {
            abort(400, 'Debe seleccionar al menos un tipo de cartera.');
        }
        $sedeId = $this->resolveSedeId();
        $resultado = $carteraReportService->generar($fechaCarbon, $sedeId);
        $secciones = array_intersect_key($resultado['secciones'], array_flip($tiposArray));
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cartera');
        $styles = $this->getStyles();
        $row = 1;
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'REPORTE DE CARTERA');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;
        $totalesGenerales = ['monto_entregado' => 0.0, 'total' => 0.0, 'pagado' => 0.0, 'saldo' => 0.0];
        foreach ($secciones as $key => $seccion) {
            if (empty($seccion['creditos'])) {
                continue;
            }
            $sheet->setCellValue('A' . $row, $seccion['titulo'] . ' (' . count($seccion['creditos']) . ' créditos)');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            $headers = ['Tipo', 'Cliente', 'Zona', 'Monto Entregado', 'Monto Total', 'Pagado', 'Saldo', 'Fecha Entrega', 'Vencimiento', 'Días'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . $row, $h);
                $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
            }
            $row++;
            foreach ($seccion['creditos'] as $item) {
                $this->writeDataRow($sheet, $row, [$item['tipo'], $item['cliente'], $item['zona'], $item['monto_entregado'], $item['total'], $item['pagado'], $item['saldo'], $item['fecha'], $item['fecha_venc'], $item['dias']], 10);
                foreach (['D', 'E', 'F', 'G', 'J'] as $column) {
                    $sheet->getStyle($column . $row)->getAlignment()->setHorizontal('right');
                }
                $sheet->getStyle('D' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $totalesGenerales['monto_entregado'] += $item['monto_entregado'];
                $totalesGenerales['total'] += $item['total'];
                $totalesGenerales['pagado'] += $item['pagado'];
                $totalesGenerales['saldo'] += $item['saldo'];
                $row++;
            }
            $sheet->setCellValue('C' . $row, 'TOTAL:');
            foreach (['D' => 'monto_entregado', 'E' => 'total', 'F' => 'pagado', 'G' => 'saldo'] as $column => $field) {
                $sheet->setCellValue($column . $row, array_sum(array_column($seccion['creditos'], $field)));
                $sheet->getStyle($column . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getStyle('C' . $row . ':G' . $row)->applyFromArray($styles['total']);
            $row += 2;
        }
        $sheet->setCellValue('C' . $row, 'TOTAL GENERAL:');
        foreach (['D' => 'monto_entregado', 'E' => 'total', 'F' => 'pagado', 'G' => 'saldo'] as $column => $field) {
            $sheet->setCellValue($column . $row, $totalesGenerales[$field]);
            $sheet->getStyle($column . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle('C' . $row . ':G' . $row)->applyFromArray($styles['total']);
        $widths = [18, 34, 18, 18, 16, 16, 16, 16, 16, 10];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $sheet->freezePane('A3');
        return $this->downloadSpreadsheet($spreadsheet, "Reporte_Cartera_{$fecha}");
    }

    // ─── CREDITOS VENCIDOS ───
    public function vencidosExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'view_any_reporte::creditos::vencidos');

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $sedeId = $this->resolveSedeId();

        $fechaCarbonDesde = $fechaDesde ? Carbon::createFromFormat('Y-m-d', $fechaDesde) : now();
        $fechaCarbonHasta = $fechaHasta ? Carbon::createFromFormat('Y-m-d', $fechaHasta) : $fechaCarbonDesde;

        $creditos = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->whereHas('proposicion', fn($q) => $q->where('SaldoPendiente', '>', 0))
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->when($fechaDesde, fn($q) => $q->whereDate('Credito.FechaVencimiento', '>=', $fechaCarbonDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('Credito.FechaVencimiento', '<=', $fechaCarbonHasta))
            ->with(['proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.zona'])
            ->orderBy('Credito.FechaVencimiento')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vencidos');
        $styles = $this->getStyles();
        $row = 1;

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'CREDITOS VENCIDOS');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $headers = ['DNI', 'Tipo', 'Cliente', 'Zona', 'Total', 'Pagado', 'Saldo', 'Vencimiento'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;

        $totalGeneral = 0; $totalPagado = 0; $totalSaldo = 0;
        foreach ($creditos as $credito) {
            $pagado = Pago::whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
                ->where('Activo', 1)->sum('MontoPagado');
            $total = $credito->proposicion->MontoTotalPagar ?? 0;
            $saldo = $total - $pagado;

            $this->writeDataRow($sheet, $row, [
                $credito->proposicion?->cliente?->DNI ?? '-',
                $credito->proposicion?->tipoCredito?->Descripcion ?? '-',
                $credito->proposicion?->cliente?->NombresApellidos ?? '-',
                $credito->proposicion?->zona?->Nombre ?? '-',
                $total, $pagado, $saldo,
                $credito->FechaVencimiento?->format('d/m/Y') ?? '-',
            ], 8);

            foreach (['E', 'F', 'G'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal('right');
            }

            $totalGeneral += $total; $totalPagado += $pagado; $totalSaldo += $saldo;
            $row++;
        }

        $sheet->setCellValue('D' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('E' . $row, $totalGeneral);
        $sheet->setCellValue('F' . $row, $totalPagado);
        $sheet->setCellValue('G' . $row, $totalSaldo);
        $sheet->getStyle('D' . $row . ':H' . $row)->applyFromArray($styles['total']);

        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setWidth(18);

        return $this->downloadSpreadsheet($spreadsheet, "Creditos_Vencidos_{$fechaCarbonDesde->format('d-m-Y')}");
    }

    // ─── CLIENTES ATRASO ───
    public function atrasoExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'view_any_reporte::clientes::atraso');

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $clienteId = $request->get('cliente_id');
        $sedeId = $this->resolveSedeId();

        $query = Credito::withoutGlobalScopes()
            ->where('Activo', 1)
            ->whereHas('proposicion', fn($q) => $q->where('SaldoPendiente', '>', 0))
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->select('Credito.*')
            ->selectRaw("DATEDIFF(NOW(), COALESCE((SELECT MAX(FechaPago) FROM pago WHERE pago.CreditoID = Credito.CreditoID AND pago.Activo = 1), FechaGeneracion)) as dias_atraso_calc")
            ->havingRaw('dias_atraso_calc >= 1');

        if ($clienteId) $query->whereHas('proposicion', fn($q) => $q->where('ClienteID', $clienteId));
        if ($fechaDesde) $query->whereDate('FechaVencimiento', '>=', $fechaDesde);
        if ($fechaHasta) $query->whereDate('FechaVencimiento', '<=', $fechaHasta);

        $creditos = $query->with(['proposicion.cliente', 'proposicion.zona'])
            ->orderByRaw('dias_atraso_calc DESC')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Atraso');
        $styles = $this->getStyles();
        $row = 1;

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'CLIENTES CON DIAS DE ATRASO');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $headers = ['Código', 'DNI', 'Cliente', 'Zona', 'Monto', 'Monto+Interés', 'Saldo', 'Días Atraso', 'Vencimiento'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;

        foreach ($creditos as $credito) {
            $ultimoPago = $credito->pagos()->where('Activo', 1)->max('FechaPago');
            $fechaRef = $ultimoPago ?? $credito->FechaGeneracion;
            $diasAtraso = $fechaRef
                ? \App\Services\DiasHabilesCalculator::contarDiasHabiles(
                    \Carbon\Carbon::parse($fechaRef)->addDay(), now(), $credito->SedeID
                )
                : 0;
            $this->writeDataRow($sheet, $row, [
                $credito->proposicion->CodigoCredito ?? '-',
                $credito->proposicion->cliente->DNI ?? '-',
                $credito->proposicion->cliente->NombresApellidos ?? '-',
                $credito->proposicion->zona->Nombre ?? '-',
                $credito->proposicion->MontoTotal ?? 0,
                $credito->proposicion->MontoTotalPagar ?? 0,
                $credito->proposicion->SaldoPendiente ?? 0,
                $diasAtraso,
                $credito->FechaVencimiento?->format('d/m/Y') ?? '-',
            ], 9);
            foreach (['E', 'F', 'G', 'H'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal('right');
            }
            $row++;
        }

        foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setWidth(16);

        return $this->downloadSpreadsheet($spreadsheet, "Clientes_Atraso_" . now()->format('d-m-Y'));
    }

    // ─── CLIENTES INACTIVOS ───
    public function inactivosExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'view_any_reporte::clientes::inactivos');

        $nombre = $request->get('nombre');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $sedeId = $this->resolveSedeId();

        $clientes = \Illuminate\Support\Facades\DB::table('Cliente')
            ->select('Cliente.ClienteID', 'Cliente.DNI', 'Cliente.NombresApellidos')
            ->selectRaw("MAX(Credito.FechaSaldamiento) as fecha_saldado")
            ->selectRaw("DATEDIFF(NOW(), MAX(Credito.FechaSaldamiento)) as dias_inactivo")
            ->selectRaw("(SELECT pc.CodigoCredito FROM ProposicionCredito pc
                JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
                WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO'
                ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_codigo")
            ->selectRaw("(SELECT pc.MontoTotal FROM ProposicionCredito pc
                JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
                WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO'
                ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_monto")
            ->selectRaw("(SELECT pc.MontoTotalPagar FROM ProposicionCredito pc
                JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
                WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO'
                ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_monto_total")
            ->selectRaw("(SELECT c.FechaGeneracion FROM ProposicionCredito pc
                JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
                WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO'
                ORDER BY c.FechaSaldamiento DESC LIMIT 1) as fecha_generado")
            ->selectRaw("(SELECT z.Nombre FROM ProposicionCredito pc
                JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
                JOIN Zona z ON z.ZonaID = pc.ZonaID
                WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO'
                ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultima_zona")
            ->join('ProposicionCredito as prop', 'prop.ClienteID', '=', 'Cliente.ClienteID')
            ->join('Credito', function ($join) {
                $join->on('Credito.ProposicionCreditoID', '=', 'prop.ProposicionCreditoID')
                     ->where('Credito.EstatusCreditoFinal', '=', 'SALDADO');
            })
            ->where('Cliente.Activo', true)
            ->when($sedeId, fn($q) => $q->where('Cliente.SedeID', $sedeId))
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)
                  ->from('ProposicionCredito as p2')
                  ->join('Credito as c2', 'c2.ProposicionCreditoID', '=', 'p2.ProposicionCreditoID')
                  ->whereColumn('p2.ClienteID', 'Cliente.ClienteID')
                  ->where('p2.Activo', true)->where('c2.Activo', true)
                  ->where('c2.EstatusCreditoFinal', '!=', 'SALDADO');
            });

        if ($nombre) {
            $clientes->where(function ($q) use ($nombre) {
                $q->where('Cliente.NombresApellidos', 'like', "%{$nombre}%")
                  ->orWhere('Cliente.DNI', 'like', "%{$nombre}%");
            });
        }
        if ($fechaDesde) $clientes->havingRaw('MAX(Credito.FechaSaldamiento) >= ?', [$fechaDesde]);
        if ($fechaHasta) $clientes->havingRaw('MAX(Credito.FechaSaldamiento) <= ?', [$fechaHasta]);

        $clientes = $clientes->groupBy('Cliente.ClienteID', 'Cliente.DNI', 'Cliente.NombresApellidos')
            ->havingRaw('dias_inactivo >= 1')
            ->orderByRaw('dias_inactivo DESC')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inactivos');
        $styles = $this->getStyles();
        $row = 1;

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'CLIENTES INACTIVOS');
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $headers = ['DNI', 'Cliente', 'Zona', 'Último Crédito', 'F. Generación', 'Monto', 'Monto+Interés', 'F. Saldado', 'Días Inactivo'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;

        $totalMonto = 0; $totalMontoTotal = 0;
        foreach ($clientes as $cliente) {
            $diasInactivo = $cliente->fecha_saldado
                ? \App\Services\DiasHabilesCalculator::contarDiasHabiles(
                    \Carbon\Carbon::parse($cliente->fecha_saldado)->addDay(), now()
                )
                : 0;
            $this->writeDataRow($sheet, $row, [
                $cliente->DNI ?? '-', $cliente->NombresApellidos ?? '-', $cliente->ultima_zona ?? '-',
                $cliente->ultimo_codigo ?? '-',
                $cliente->fecha_generado ? Carbon::parse($cliente->fecha_generado)->format('d/m/Y') : '-',
                (float)($cliente->ultimo_monto ?? 0), (float)($cliente->ultimo_monto_total ?? 0),
                $cliente->fecha_saldado ? Carbon::parse($cliente->fecha_saldado)->format('d/m/Y') : '-',
                $diasInactivo,
            ], 9);
            foreach (['F', 'G', 'I'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal('right');
            }
            $totalMonto += (float)($cliente->ultimo_monto ?? 0);
            $totalMontoTotal += (float)($cliente->ultimo_monto_total ?? 0);
            $row++;
        }

        $sheet->setCellValue('E' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('F' . $row, $totalMonto);
        $sheet->setCellValue('G' . $row, $totalMontoTotal);
        $sheet->getStyle('E' . $row . ':G' . $row)->applyFromArray($styles['total']);

        foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setWidth(16);

        return $this->downloadSpreadsheet($spreadsheet, "Clientes_Inactivos_" . now()->format('d-m-Y'));
    }

    // ─── REPORTE DE CREDITOS ───
    public function creditosExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'reporte_creditos');

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        if (!$fechaDesde || !$fechaHasta) { abort(400, 'Debe especificar un rango de fechas.'); }

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $fechaCarbonDesde = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $fechaCarbonHasta = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();

        if ($fechaCarbonDesde->diffInDays($fechaCarbonHasta) > 365) {
            abort(400, 'El rango maximo permitido es de 1 ano (365 dias).');
        }

        $sedeId = $this->resolveSedeId();

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $filename = "Reporte_Creditos_{$fechaDesde}_{$fechaHasta}.xlsx";
        $writer->openToBrowser($filename);

        $headerStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(\OpenSpout\Common\Entity\Style\Color::WHITE)
            ->setBackgroundColor('4472C4')
            ->setCellAlignment(\OpenSpout\Common\Entity\Style\CellAlignment::CENTER);
        $totalStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setBackgroundColor('E8F0FE');

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(
            ["REPORTE DE CREDITOS - {$fechaDesde} AL {$fechaHasta}"]
        ));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(
            ['DNI', 'Nombres', 'Monto Entregado', 'Interes', 'Monto Total', 'Fecha Entrega', 'Saldo', 'Cobro Montado', 'Fecha Vencimiento', 'Tipo de Credito', 'Dias'],
            $headerStyle
        ));

        $query = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->whereBetween('Credito.FechaGeneracion', [$fechaCarbonDesde, $fechaCarbonHasta])
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select(
                'Cliente.DNI', 'Cliente.NombresApellidos',
                'ProposicionCredito.MontoTotal', 'ProposicionCredito.MontoInteres',
                'ProposicionCredito.MontoTotalPagar', 'ProposicionCredito.SaldoPendiente',
                'ProposicionCredito.MontoCuota', 'ProposicionCredito.Plazo',
                'Credito.FechaGeneracion', 'Credito.FechaVencimiento',
                'TipoCredito.Descripcion as TipoCreditoDescripcion'
            )
            ->orderBy('Credito.FechaGeneracion');

        $totalMonto = 0; $totalInteres = 0; $totalMontoPagar = 0; $totalSaldo = 0;
        $count = 0;

        $query->chunk(500, function ($chunk) use ($writer, &$totalMonto, &$totalInteres, &$totalMontoPagar, &$totalSaldo, &$count) {
            foreach ($chunk as $c) {
                $montoTotal    = (float) ($c->MontoTotal ?? 0);
                $montoInteres  = (float) ($c->MontoInteres ?? 0);
                $montoTotalPagar = (float) ($c->MontoTotalPagar ?? 0);
                $saldo         = (float) ($c->SaldoPendiente ?? 0);

                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                    $c->DNI ?? '-',
                    $c->NombresApellidos ?? '-',
                    $montoTotal,
                    $montoInteres,
                    $montoTotalPagar,
                    $c->FechaGeneracion ? Carbon::parse($c->FechaGeneracion)->format('d/m/Y') : '-',
                    $saldo,
                    (float) ($c->MontoCuota ?? 0),
                    $c->FechaVencimiento ? Carbon::parse($c->FechaVencimiento)->format('d/m/Y') : '-',
                    $c->TipoCreditoDescripcion ?? '-',
                    $c->Plazo ?? '-',
                ]));

                $totalMonto += $montoTotal;
                $totalInteres += $montoInteres;
                $totalMontoPagar += $montoTotalPagar;
                $totalSaldo += $saldo;
                $count++;
            }
        });

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(
            ["TOTAL: {$count} créditos", '', $totalMonto, $totalInteres, $totalMontoPagar, '', $totalSaldo, '', '', '', ''],
            $totalStyle
        ));

        $writer->close();
    }
    public function eficienciaCobranzaExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'eficiencia_cobranza');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        if (!$fechaDesde || !$fechaHasta) {
            abort(400, 'Rango de fechas no proporcionado');
        }
        $desde = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $hasta = Carbon::createFromFormat('Y-m-d', $fechaHasta)->startOfDay();
        if ($hasta->lt($desde)) {
            abort(400, 'La fecha hasta no puede ser menor a la fecha desde');
        }
        if ($desde->diffInDays($hasta) > 365) {
            abort(400, 'El rango maximo permitido es de 1 ano (365 dias).');
        }
        $sedeId = $this->resolveSedeId();
        $promotores = PromotorCobrador::withoutGlobalScopes()->with(['zona.ciudad'])->where('Activo', true)->whereNotNull('ZonaID')->when($sedeId, fn($query) => $query->where('SedeID', $sedeId))->orderBy('ZonaID')->orderBy('Descripcion')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Eficiencia Cobranza');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(8);
        $titleStyle = ['font' => ['bold' => false, 'name' => 'Calibri', 'size' => 14, 'color' => ['rgb' => '000000']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92D050']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $headerStyle = ['font' => ['bold' => true, 'name' => 'Tahoma', 'size' => 8, 'color' => ['rgb' => '000000']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $dataStyle = ['font' => ['name' => 'Arial', 'size' => 8], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $realStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]];
        $headers = ['FECHA', 'CLIENTES ACTIVOS', 'MONTO DE COBR', 'CLTS Q CANCEL', 'NP', 'SCR', 'TOTAL', '% SCR', 'PROMD', '% REAL'];
        $row = 1;
        if ($promotores->isEmpty()) {
            $sheet->mergeCells('A1:J1');
            $sheet->setCellValue('A1', 'NO HAY PROMOTORES COBRADORES ACTIVOS PARA EL RANGO SELECCIONADO');
            $sheet->getStyle('A1:J1')->applyFromArray($titleStyle);
            return $this->downloadSpreadsheet($spreadsheet, "Eficiencia_Cobranza_{$fechaDesde}_{$fechaHasta}");
        }
        foreach ($promotores as $promotor) {
            $titulo = mb_strtoupper(trim(($promotor->zona?->Nombre ?? 'SIN ZONA') . ' - ' . $promotor->Descripcion));
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", $titulo);
            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($titleStyle);
            $sheet->getRowDimension($row)->setRowHeight(24);
            $row++;
            foreach ($headers as $index => $header) {
                $col = chr(65 + $index);
                $sheet->setCellValue("{$col}{$row}", $header);
                $sheet->getStyle("{$col}{$row}")->applyFromArray($headerStyle);
            }
            $sheet->getStyle("J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8CBAD');
            $row++;
            $inicioDatos = $row;
            $fecha = $desde->copy();
            while ($fecha->lte($hasta)) {
                if (!\App\Services\CalendarioLaboralService::esLaborable($fecha, $promotor->SedeID)) {
                    $fecha->addDay();
                    continue;
                }
                $clasificacion = $this->clasificarClientesEficiencia((int) $promotor->PromotorCobradorID, (int) $promotor->ZonaID, $fecha, $sedeId);
                $clientesActivos = $clasificacion['activos']->count();
                $montoCobrado = $clasificacion['monto_cobrado'];
                $clientesCancelaron = $clasificacion['cancelaron']->count();
                $scr = $clasificacion['scr']->count();
                $np = $clasificacion['np']->count();
                $total = $clientesCancelaron + $np + $scr;
                $porcentajeScr = $clientesActivos > 0 && $scr > 0 ? round($scr / $clientesActivos * 100, 2) : null;
                $promedio = $clientesActivos > 0 ? round($clientesCancelaron / $clientesActivos * 100, 2) : 0;
                $real = round(($porcentajeScr ?? 0) + $promedio, 2);
                $valores = [$fecha->format('j/n/Y'), $clientesActivos, $montoCobrado, $clientesCancelaron, $np, $scr, $total, $porcentajeScr ?? '-', $promedio, $real];
                foreach ($valores as $index => $valor) {
                    $col = chr(65 + $index);
                    $sheet->setCellValue("{$col}{$row}", $valor);
                    $sheet->getStyle("{$col}{$row}")->applyFromArray($dataStyle);
                }
                $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('0000FF');
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("H{$row}:J{$row}")->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle("J{$row}")->applyFromArray($realStyle);
                $sheet->getStyle("A{$row}:J{$row}")->getFont()->setName('Arial')->setSize(8);
                $row++;
                $fecha->addDay();
            }
            if ($row > $inicioDatos) {
                $sheet->setCellValue("I{$row}", 'PROMEDIO % REAL');
                $sheet->setCellValue("J{$row}", "=AVERAGE(J{$inicioDatos}:J" . ($row - 1) . ')');
                $sheet->getStyle("I{$row}:J{$row}")->getFont()->setBold(true)->setName('Arial')->setSize(8);
                $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.00');
            }
            $row += 4;
        }
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        return $this->downloadSpreadsheet($spreadsheet, "Eficiencia_Cobranza_{$fechaDesde}_{$fechaHasta}");
    }
    private function baseCreditosEficiencia(int $promotorId, int $zonaId, ?int $sedeId)
    {
        return DB::table('Credito')->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')->where('ProposicionCredito.ZonaID', $zonaId)->where('ProposicionCredito.Activo', 1)->where('ProposicionCredito.Estado', 'APROBADO')->where('ProposicionCredito.FueRefinanciada', 0)->where('ProposicionCredito.Eliminado', 0)->where('Credito.Activo', 1)->when($sedeId, fn($query) => $query->where('Credito.SedeID', $sedeId));
    }
    private function basePagosEficiencia(int $promotorId, int $zonaId, Carbon $fecha, ?int $sedeId)
    {
        return DB::table('pago')->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')->where('pago.Activo', 1)->where('ProposicionCredito.ZonaID', $zonaId)->whereDate('pago.FechaPago', $fecha->toDateString())->where(function ($query) {
            $query->where('pago.EsPagoAutomatico', 0)->orWhereNull('pago.EsPagoAutomatico');
        })->when($sedeId, fn($query) => $query->where('pago.SedeID', $sedeId));
    }
    private function clasificarClientesEficiencia(int $promotorId, int $zonaId, Carbon $fecha, ?int $sedeId): array
    {
        $terminales = ['SALDADO', 'REFINANCIADO', 'ELIMINADO'];
        $limiteSalida = $fecha->copy()->subDays(7)->startOfDay();
        $creditos = $this->baseCreditosEficiencia($promotorId, $zonaId, $sedeId)->whereDate('Credito.FechaGeneracion', '<=', $fecha->toDateString())->where(function ($query) use ($terminales, $limiteSalida, $fecha) {
            $query->whereNotIn('Credito.EstatusCreditoFinal', $terminales)->orWhere(function ($salidas) use ($limiteSalida, $fecha) {
                $salidas->where('Credito.EstatusCreditoFinal', 'SALDADO')->whereBetween('Credito.FechaSaldamiento', [$limiteSalida, $fecha->copy()->endOfDay()]);
            });
        })->select(['ProposicionCredito.ClienteID as cliente_id', 'Cliente.DNI as dni', 'Cliente.NombresApellidos as cliente', 'ProposicionCredito.CodigoCredito as codigo_credito', 'Credito.CreditoID as credito_id', 'Credito.FechaGeneracion as fecha_generacion', 'Credito.FechaSaldamiento as fecha_saldamiento', 'Credito.EstatusCreditoFinal as estado_credito'])->orderByDesc('Credito.FechaGeneracion')->get()->groupBy('cliente_id');
        $pagos = $this->basePagosEficiencia($promotorId, $zonaId, $fecha, $sedeId)->select(['ProposicionCredito.ClienteID as cliente_id', 'Cliente.DNI as dni', 'Cliente.NombresApellidos as cliente', 'ProposicionCredito.CodigoCredito as codigo_credito', 'Credito.CreditoID as credito_id', 'Credito.FechaGeneracion as fecha_generacion', 'pago.MontoPagado as monto_pagado'])->get()->groupBy('cliente_id');
        return $this->clasificarRegistrosEficiencia($creditos, $pagos, $terminales);
    }
    private function clasificarRegistrosEficiencia($creditos, $pagos, array $terminales): array
    {
        $activos = collect();
        $salidas = collect();
        foreach ($creditos as $clienteId => $creditosCliente) {
            $creditoVigente = $creditosCliente->first(fn($credito) => !in_array(mb_strtoupper((string) $credito->estado_credito), $terminales, true));
            $registro = $creditoVigente ?? $creditosCliente->first();
            if (!$registro) {
                continue;
            }
            $activos->put((int) $clienteId, $registro);
            // Una salida solo es SCR si el cliente ya no conserva otro credito vigente.
            if (!$creditoVigente) {
                $salidas->put((int) $clienteId, $registro);
            }
        }
        $cancelaron = collect();
        foreach ($pagos as $clienteId => $pagosCliente) {
            $clienteId = (int) $clienteId;
            if (!$activos->has($clienteId) || $salidas->has($clienteId)) {
                continue;
            }
            $registro = clone $pagosCliente->first();
            $registro->monto_pagado = (float) $pagosCliente->sum('monto_pagado');
            $registro->codigo_credito = $pagosCliente->pluck('codigo_credito')->filter()->unique()->implode(', ');
            $cancelaron->put($clienteId, $registro);
        }
        $np = $activos->except($cancelaron->keys()->merge($salidas->keys())->all());
        return ['activos' => $activos, 'cancelaron' => $cancelaron, 'np' => $np, 'scr' => $salidas, 'monto_cobrado' => (float) $pagos->flatten(1)->sum('monto_pagado')];
    }
    public function eficienciaCobranzaDetalleExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'eficiencia_cobranza');
        $fechaValor = $request->get('fecha');
        if (!$fechaValor) {
            abort(400, 'Fecha no proporcionada');
        }
        $fecha = Carbon::createFromFormat('Y-m-d', $fechaValor)->startOfDay();
        if ($fecha->isFuture()) {
            abort(400, 'La fecha no puede ser futura');
        }
        $sedeId = $this->resolveSedeId();
        $promotores = PromotorCobrador::withoutGlobalScopes()->with('zona')->where('Activo', true)->whereNotNull('ZonaID')->when($sedeId, fn($query) => $query->where('SedeID', $sedeId))->orderBy('ZonaID')->orderBy('Descripcion')->get();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $categorias = ['cancelaron' => ['titulo' => 'Cancelaron', 'color' => '92D050'], 'np' => ['titulo' => 'No pagaron', 'color' => 'FFC000'], 'scr' => ['titulo' => 'SCR', 'color' => 'F8CBAD']];
        $headers = ['N°', 'FECHA REPORTE', 'ZONA', 'PROMOTOR', 'DNI', 'CLIENTE', 'CODIGO CREDITO', 'FECHA GENERACION', 'FECHA SALIDA', 'MONTO PAGADO'];
        foreach ($categorias as $indice => $configuracion) {
            $sheet = $indice === array_key_first($categorias) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($configuracion['titulo']);
            $sheet->mergeCells('A1:J1');
            $sheet->setCellValue('A1', mb_strtoupper("DETALLE {$configuracion['titulo']} - {$fecha->format('d/m/Y')}"));
            $sheet->getStyle('A1:J1')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $configuracion['color']]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
            foreach ($headers as $columna => $header) {
                $celda = chr(65 + $columna) . '3';
                $sheet->setCellValue($celda, $header);
                $sheet->getStyle($celda)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD966']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            }
            $fila = 4;
            $numero = 1;
            foreach ($promotores as $promotor) {
                $clasificacion = $this->clasificarClientesEficiencia((int) $promotor->PromotorCobradorID, (int) $promotor->ZonaID, $fecha, $sedeId);
                foreach ($clasificacion[$indice]->sortBy('cliente') as $registro) {
                    $valores = [$numero++, $fecha->format('d/m/Y'), $promotor->zona?->Nombre ?? 'SIN ZONA', $promotor->Descripcion, $registro->dni ?? '-', $registro->cliente ?? '-', $registro->codigo_credito ?? '-', filled($registro->fecha_generacion ?? null) ? Carbon::parse($registro->fecha_generacion)->format('d/m/Y') : '-', filled($registro->fecha_saldamiento ?? null) ? Carbon::parse($registro->fecha_saldamiento)->format('d/m/Y') : '-', $indice === 'cancelaron' ? (float) ($registro->monto_pagado ?? 0) : ''];
                    foreach ($valores as $columna => $valor) {
                        $celda = chr(65 + $columna) . $fila;
                        $sheet->setCellValue($celda, $valor);
                        $sheet->getStyle($celda)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
                    }
                    if ($indice === 'cancelaron') {
                        $sheet->getStyle("J{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $fila++;
                }
            }
            if ($fila === 4) {
                $sheet->mergeCells('A4:J4');
                $sheet->setCellValue('A4', 'No hay clientes en esta categoria para la fecha seleccionada.');
                $sheet->getStyle('A4:J4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $fila++;
            }
            $sheet->setAutoFilter('A3:J' . ($fila - 1));
            $sheet->freezePane('A4');
            $anchos = [7, 15, 18, 28, 13, 34, 18, 17, 15, 16];
            foreach ($anchos as $columna => $ancho) {
                $sheet->getColumnDimension(chr(65 + $columna))->setWidth($ancho);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);
        return $this->downloadSpreadsheet($spreadsheet, "Detalle_Eficiencia_Cobranza_{$fechaValor}");
    }
    public function proyeccionExcel(Request $request)
    {
        $this->authorizeGerenciaOrPermission($request, 'reporte_proyeccion');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        if (!$fechaDesde || !$fechaHasta) {
            abort(400, 'Debe especificar un rango de fechas.');
        }
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $fechaCarbonDesde = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $fechaCarbonHasta = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();
        if ($fechaCarbonDesde->diffInDays($fechaCarbonHasta) > 365) {
            abort(400, 'El rango maximo permitido es de 1 ano (365 dias).');
        }
        $sedeId = $this->resolveSedeId();
        $sedeNombre = $sedeId ? Sede::find($sedeId)?->Nombre ?? 'SEDE' : 'TODAS LAS SEDES';
        $query = Credito::withoutGlobalScopes()->where('Credito.Activo', 1)->whereBetween('Credito.FechaVencimiento', [$fechaCarbonDesde, $fechaCarbonHasta])->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')->select('Cliente.DNI', 'Cliente.NombresApellidos', 'ProposicionCredito.MontoTotal', 'ProposicionCredito.TasaInteres', 'ProposicionCredito.MontoTotalPagar', 'ProposicionCredito.SaldoPendiente', 'ProposicionCredito.Plazo', 'ProposicionCredito.MontoInteres', 'Credito.FechaGeneracion', 'Credito.FechaVencimiento', 'TipoCredito.Descripcion as TipoCreditoDescripcion', DB::raw('(SELECT COALESCE(SUM(p.MontoPagado), 0) FROM pago p
                          WHERE p.CreditoID = Credito.CreditoID AND p.Activo = 1 AND p.EsMora = 0) as total_pagado'))->orderBy('Credito.FechaVencimiento');
        $creditostotal = $query->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proyeccion');
        $styles = $this->getStyles();
        $row = 1;
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', "REPORTE PROYECCION POR VENCIMIENTO - {$sedeNombre} - {$fechaCarbonDesde->format('d/m/Y')} AL {$fechaCarbonHasta->format('d/m/Y')}");
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;
        $headers = ['DNI', 'Nombres y Apellidos', 'Monto Prestado', '% Interes', 'Total (Monto + Interes)', 'Fecha de Giro', 'Total Pagado', 'Saldo', 'Fecha de Vencimiento', 'Tipo de Credito', 'Dias (Plazo)', 'Interes a Cobrar'];
        $colCount = count($headers);
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalMonto = 0;
        $totalMontoPagar = 0;
        $totalPagado = 0;
        $totalSaldo = 0;
        $totalInteres = 0;
        $count = 0;
        foreach ($creditostotal as $c) {
            $montoTotal = (float) ($c->MontoTotal ?? 0);
            $montoTotalPagar = (float) ($c->MontoTotalPagar ?? 0);
            $totalPagadoC = (float) ($c->total_pagado ?? 0);
            $saldo = (float) ($c->SaldoPendiente ?? 0);
            $montoInteres = (float) ($c->MontoInteres ?? 0);
            $this->writeDataRow($sheet, $row, [$c->DNI ?? '-', $c->NombresApellidos ?? '-', $montoTotal, ($c->TasaInteres ?? 0) . '%', $montoTotalPagar, $c->FechaGeneracion ? Carbon::parse($c->FechaGeneracion)->format('d/m/Y') : '-', $totalPagadoC, $saldo, $c->FechaVencimiento ? Carbon::parse($c->FechaVencimiento)->format('d/m/Y') : '-', $c->TipoCreditoDescripcion ?? '-', $c->Plazo ?? '-', $montoInteres], $colCount);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal('right');
            $totalMonto += $montoTotal;
            $totalMontoPagar += $montoTotalPagar;
            $totalPagado += $totalPagadoC;
            $totalSaldo += $saldo;
            $totalInteres += $montoInteres;
            $count++;
            $row++;
        }
        $sheet->setCellValue('A' . $row, "TOTAL: {$count} creditos");
        $sheet->setCellValue('C' . $row, $totalMonto);
        $sheet->setCellValue('E' . $row, $totalMontoPagar);
        $sheet->setCellValue('G' . $row, $totalPagado);
        $sheet->setCellValue('H' . $row, $totalSaldo);
        $sheet->setCellValue('L' . $row, $totalInteres);
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($styles['total']);
        $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal('right');
        $widths = [12, 30, 15, 10, 18, 13, 13, 12, 15, 18, 12, 14];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
        }
        return $this->downloadSpreadsheet($spreadsheet, "Reporte_Proyeccion_{$fechaCarbonDesde->format('d-m-Y')}_{$fechaCarbonHasta->format('d-m-Y')}");
    }
    public function carteraGeneralExcel(Request $request, CarteraReportService $carteraReportService)
    {
        $this->authorizeGerenciaOrPermission($request, 'reporte_cartera_general');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        if (!$fechaDesde || !$fechaHasta) {
            abort(400, 'Debe especificar un rango de fechas.');
        }
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $fechaCarbonDesde = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $fechaCarbonHasta = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();
        if ($fechaCarbonDesde->gt($fechaCarbonHasta)) {
            abort(400, 'La fecha desde no puede ser mayor a la fecha hasta.');
        }
        $sedeId = $this->resolveSedeId();
        if (!$sedeId) {
            abort(400, 'Debe seleccionar una sede.');
        }
        $sede = Sede::find($sedeId);
        $sedeNombre = $sede?->Nombre ?? 'SEDE';
        $ciudadId = $request->get('ciudad_id') ? (int) $request->get('ciudad_id') : null;
        $zonaId = $request->get('zona_id') ? (int) $request->get('zona_id') : null;
        $resultado = $carteraReportService->generar($fechaCarbonHasta, $sedeId, $ciudadId, $zonaId, $fechaCarbonDesde);
        $secciones = $resultado['secciones'];
        $totalGeneral = (float) $resultado['totalGeneralSaldo'];
        $zonas = \App\Models\Zona::withoutGlobalScopes()->where('Activo', 1)->where('SedeID', $sedeId)->when($ciudadId, fn($query) => $query->where('CiudadID', $ciudadId))->when($zonaId, fn($query) => $query->where('ZonaID', $zonaId))->orderBy('Nombre')->pluck('Nombre')->all();
        $zonasConDatos = collect($secciones)->flatMap(fn(array $seccion) => array_keys($seccion['porZona']))->unique()->all();
        $zonas = collect(array_merge($zonas, $zonasConDatos))->filter()->unique()->sort()->values()->all();
        $ciudadNombre = null;
        if ($ciudadId) {
            $ciudadNombre = \App\Models\Ciudad::withoutGlobalScopes()->find($ciudadId)?->Nombre;
        }
        // ─── Construir Excel ───
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cartera General');
        $row = 1;
        // ===== TITULO PRINCIPAL =====
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'CARTERA GENERAL TOTAL');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']], 'alignment' => ['horizontal' => 'center', 'vertical' => 'center']]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $row = 3;
        // ===== INFORMACION DEL REPORTE =====
        $detalle = "Sede: {$sedeNombre}" . ($ciudadNombre ? "  |  Ciudad: {$ciudadNombre}" : '  |  Ciudad: TODAS') . ($zonaId ? '  |  Zona: ' . (\App\Models\Zona::withoutGlobalScopes()->find($zonaId)?->Nombre ?? '') : '  |  Zona: TODAS');
        $sheet->setCellValue('A' . $row, $detalle);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $row++;
        $sheet->setCellValue('A' . $row, "Giros: {$fechaCarbonDesde->format('d/m/Y')} al {$fechaCarbonHasta->format('d/m/Y')}  |  Emitido: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A' . $row)->getFont()->setSize(10)->setColor(new Color('666666'));
        $row += 2;
        $headers = ['CARTERA / ZONA', 'MONTO POR CARTERA', 'MONTO POR ZONA', '% DEL TIPO DE CARTERA', '% ZONA / CARTERA TOTAL', '% CARTERA / CARTERA TOTAL'];
        foreach ($headers as $index => $header) {
            $cell = chr(65 + $index) . $row;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']], 'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        }
        $sheet->getRowDimension($row)->setRowHeight(34);
        $row++;
        $titulosCartera = ['pesada' => 'CARTERA CASTIGADA / PESADA (181+ DÍAS)', 'morosa' => 'CARTERA MOROSA (8 - 180 DÍAS)', 'vencida' => 'CARTERA VENCIDA (1 - 7 DÍAS)', 'no_vencida' => 'CARTERA NO VENCIDA'];
        foreach (['pesada', 'morosa', 'vencida', 'no_vencida'] as $tipo) {
            $totalCartera = (float) $secciones[$tipo]['totalSaldo'];
            $sheet->setCellValue('A' . $row, $titulosCartera[$tipo]);
            $sheet->setCellValue('B' . $row, $totalCartera);
            $sheet->setCellValue('F' . $row, $totalGeneral > 0 ? $totalCartera / $totalGeneral : 0);
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1F4E78']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2F0D9']], 'alignment' => ['vertical' => 'center'], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('0.00%');
            $row++;
            foreach ($zonas as $zonaNombre) {
                $montoZona = (float) ($secciones[$tipo]['porZona'][$zonaNombre] ?? 0);
                $sheet->setCellValue('A' . $row, $zonaNombre);
                $sheet->setCellValue('C' . $row, $montoZona);
                $sheet->setCellValue('D' . $row, $totalCartera > 0 ? $montoZona / $totalCartera : 0);
                $sheet->setCellValue('E' . $row, $totalGeneral > 0 ? $montoZona / $totalGeneral : 0);
                $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray(['font' => ['size' => 10], 'alignment' => ['vertical' => 'center'], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                $sheet->getStyle('A' . $row)->getAlignment()->setIndent(1);
                $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('D' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('0.00%');
                $row++;
            }
        }
        $sheet->setCellValue('A' . $row, 'TOTAL GENERAL');
        $sheet->setCellValue('B' . $row, $totalGeneral);
        $sheet->setCellValue('F' . $row, $totalGeneral > 0 ? 1 : 0);
        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D9E2F3']], 'alignment' => ['vertical' => 'center'], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('0.00%');
        $widths = [38, 19, 18, 22, 23, 25];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $sheet->freezePane('A7');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        return $this->downloadSpreadsheet($spreadsheet, "Cartera_General_{$fechaCarbonDesde->format('d-m-Y')}_{$fechaCarbonHasta->format('d-m-Y')}");
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);
        $tempDir = storage_path('temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $filePath = "{$tempDir}/{$filename}_" . now()->format('Ymd_His') . '.xlsx';
        $writer->save($filePath);

        return response()->download($filePath, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
