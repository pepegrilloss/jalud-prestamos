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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ReporteExportController extends Controller
{
    private function resolveSedeId(): ?int
    {
        $user = auth()->user();
        $sedeParam = request()->get('sede_id');

        if ($user->isPrivileged()) {
            if ($sedeParam === '0' || $sedeParam === 'todas' || $sedeParam === '') {
                return null;
            }
            if ($sedeParam) {
                return (int) $sedeParam;
            }
            return $user->getEffectiveSedeId();
        }

        return $user->getEffectiveSedeId();
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
            ->where(function($q) {
                $q->where('pago.EsPagoAMayor', false)
                  ->orWhereNull('pago.SolicitudResolucionID');
            })
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
            ->with(['resoluciones' => fn($q) => $q->where('Estado', 'APROBADA')->with('creditoDestino.proposicion.cliente')])
            ->withSum(['resoluciones as monto_aplicado' => function($q) { $q->where('Estado', 'APROBADA'); }], 'MontoAplicar')
            ->orderBy('ExcedenteID')->get();
        $totalExcedentesDia = $excedentesDia->sum(fn($e) => (float)$e->Monto + (float)($e->monto_aplicado ?? 0));

        $ingresosRemesas = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]));
            })->when($sedeId, fn($q) => $q->where('SedeDestinoID', $sedeId))->with('sedeOrigen')->orderBy('TransferenciaID')->get();

        $salidasRemesas = TransferenciaSede::withoutGlobalScopes()->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]));
            })->when($sedeId, fn($q) => $q->where('SedeOrigenID', $sedeId))->with('sedeDestino')->orderBy('TransferenciaID')->get();

        $devolucionesDia = \App\Models\SolicitudResolucionExcedente::withoutGlobalScopes()
            ->where('SedeID', $sedeId)->where('Estado', 'APROBADA')->where('TipoResolucion', 'DEVOLUCION_EFECTIVO')
            ->whereBetween('created_at', [$fechaInicioDia, $fechaFinDia])->sum('MontoAplicar');

        // Remesas netas CA
        $ingRemCA = TransferenciaSede::withoutGlobalScopes()->where('SedeDestinoID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaDestino', 'CAJA_ABIERTA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->sum('Monto');
        $salRemCA = TransferenciaSede::withoutGlobalScopes()->where('SedeOrigenID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_ABIERTA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->sum('Monto');
        $remesasNetCajaAbierta = $ingRemCA - $salRemCA;

        $ingRemCC = TransferenciaSede::withoutGlobalScopes()->where('SedeDestinoID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaDestino', 'CAJA_CHICA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->sum('Monto');
        $salRemCC = TransferenciaSede::withoutGlobalScopes()->where('SedeOrigenID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_CHICA')
            ->where(fn($q) => $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia])))
            ->sum('Monto');
        $remesasNetCajaChica = $ingRemCC - $salRemCC;

        // Saldos Caja Chica
        $saldoInicialCajaChica = 0; $totalGastosCC = $gastos->sum('Total') + $compras->sum('Total');
        if ($sedeId) {
            $ingCC = \App\Models\MovimientoFondo::where('SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where(fn($q) => $q->where('Tipo', 'INGRESO_CAJA_CHICA')->orWhere('Tipo', 'TRASLADO_CA_A_CC'))
                ->where('Observacion', 'NOT LIKE', '%Ajuste%')->where('Observacion', 'NOT LIKE', '%Reversión%')
                ->get()->sum(fn($m) => $m->Tipo === 'TRASLADO_CA_A_CC' ? abs($m->Monto) : $m->Monto);
            $ingCC += \App\Models\MovimientoFondo::where('movimientos_fondo.SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
                ->where('movimientos_fondo.Tipo', 'RECEPCION_TRANSFERENCIA')
                ->join('transferencia_sedes', 'movimientos_fondo.TransferenciaID', '=', 'transferencia_sedes.TransferenciaID')
                ->where('transferencia_sedes.CuentaDestino', 'CAJA_CHICA')->sum('movimientos_fondo.Monto');
            $dedCC = Gasto::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('MetodoGasto', 'CAJA CHICA')->whereDate('FechaEmision', '<', $fecha)->sum('Total');
            $dedCC += Compra::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('TipoCompra', 'CONTADO')->whereDate('FechaEmision', '<', $fecha)->sum('Total');
            $dedCC += \App\Models\MovimientoFondo::where('SedeID', $sedeId)->where('Tipo', 'TRASLADO_CC_A_CA')->where('FechaMovimiento', '<', $fechaInicioDia)->sum(\DB::raw('ABS(Monto)'));
            $dedCC += \App\Models\MovimientoFondo::where('movimientos_fondo.SedeID', $sedeId)->where('FechaMovimiento', '<', $fechaInicioDia)
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
                    ->where(fn($q) => $q->where('FechaRespuesta', '<=', $limite)->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $limite)))->sum('Monto');
                $te = TransferenciaSede::withoutGlobalScopes()->where('SedeOrigenID', $sedeId)->where('Estado', 'ACEPTADO')->where('CuentaOrigen', 'CAJA_ABIERTA')
                    ->where(fn($q) => $q->where('FechaRespuesta', '<=', $limite)->orWhere(fn($q2) => $q2->whereNull('FechaRespuesta')->where('FechaTransferencia', '<=', $limite)))->sum('Monto');
                $pg = \App\Models\Pago::withoutGlobalScopes()->where('Activo', true)->where('EsPagoAMayorPorMora', false)
                    ->where(function($q) {
                        $q->where('EsPagoAMayor', false)
                          ->orWhereNull('SolicitudResolucionID');
                    })
                    ->where('FechaPago', '<=', $limite)->where('SedeID', $sedeId)->sum('MontoPagado');
                $cr = Credito::withoutGlobalScopes()->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
                    ->where('Credito.Activo', true)->where('Credito.SedeID', $sedeId)->where('Credito.FechaGeneracion', '<=', $limite)->sum('ProposicionCredito.MontoTotal');
                $ot = \App\Models\MovimientoFondo::where('SedeID', $sedeId)->where('FechaMovimiento', '<=', $limite)
                    ->whereIn('Tipo', ['INGRESO_CAPITAL', 'TRASLADO_CA_A_CC', 'TRASLADO_CC_A_CA'])->get();
                $in = $ot->where('Tipo', 'INGRESO_CAPITAL')->sum('Monto');
                $teCc = $ot->where('Tipo', 'TRASLADO_CC_A_CA')->sum(fn($m) => abs($m->Monto));
                $tsCc = $ot->where('Tipo', 'TRASLADO_CA_A_CC')->sum(fn($m) => abs($m->Monto));
                $ex = \App\Models\Excedente::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)
                    ->where(fn($q) => $q->where('Cuenta', 'Caja Abierta')->orWhereNull('Cuenta'))->where('Fecha', '<=', $limite)
                    ->withSum(['resoluciones as ma' => fn($q) => $q->where('Estado', 'APROBADA')], 'MontoAplicar')->get()
                    ->sum(fn($e) => (float)$e->Monto + (float)($e->ma ?? 0));
                $mo = \App\Models\Pago::withoutGlobalScopes()->where('Activo', true)->where('EsPagoAMayorPorMora', true)
                    ->where('FechaPago', '<=', $limite)->where('SedeID', $sedeId)->sum('MontoPagado');
                return $tr + $pg + $in + $teCc + $ex + $mo - $te - $cr - $tsCc;
            };
            $saldoInicialCajaAbierta = $calcularSaldo($fechaInicioDia->copy()->subSecond());
        }
        $totalCajaAbierta = $saldoInicialCajaAbierta + $totalAmortizaciones + $totalMoras - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;
        $saldoInicialReal = $saldoInicialCajaAbierta - 150000;
        $totalCajaAbiertaReal = $saldoInicialReal + $totalAmortizaciones + $totalMoras - $totalCreditosEmitidos + $remesasNetCajaAbierta + $totalExcedentesDia - $devolucionesDia;
        $saldoCuentaAMayor = \App\Models\Pago::withoutGlobalScopes()->where('SedeID', $sedeId)->where('Activo', true)->where('EsPagoAMayor', true)
            ->where('FechaPago', '<=', $fechaFinDia)->whereHas('solicitudResolucion', fn($q) => $q->where('TipoResolucion', '!=', 'TRASLADO_DE_PAGO'))->sum('MontoPagado');

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
        $tIng=0; foreach ($ingresosRemesas as $r) { $this->writeDataRow($sheet, $row, [$r->TransferenciaID, ($r->FechaRespuesta??$r->FechaTransferencia)?->format('d/m/Y'), $r->sedeOrigen?->Nombre, $r->CuentaDestino?:'CAJA_ABIERTA', $r->Monto], 5); $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tIng+=$r->Monto; $row++; }
        $sheet->setCellValue('D'.$row, 'TOTAL INGRESOS:'); $sheet->setCellValue('E'.$row, $tIng); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 6. SALIDA DE REMESAS
        $sheet->setCellValue('A'.$row, 'SALIDA DE REMESAS'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Nro.','Fecha','Sede Destino','Cuenta','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tSal=0; foreach ($salidasRemesas as $r) { $this->writeDataRow($sheet, $row, [$r->TransferenciaID, ($r->FechaRespuesta??$r->FechaTransferencia)?->format('d/m/Y'), $r->sedeDestino?->Nombre, $r->CuentaOrigen?:'CAJA_ABIERTA', $r->Monto], 5); $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal('right'); $tSal+=$r->Monto; $row++; }
        $sheet->setCellValue('D'.$row, 'TOTAL SALIDAS:'); $sheet->setCellValue('E'.$row, $tSal); $sheet->getStyle('D'.$row.':E'.$row)->applyFromArray($styles['total']); $row += 2;

        // 7. EXTORNOS Y DEVOLUCIONES
        $sheet->setCellValue('A'.$row, 'EXTORNOS Y DEVOLUCIONES'); $sheet->getStyle('A'.$row)->getFont()->setBold(true); $row++;
        foreach (['Operación','Fecha','CTA Cliente','Tipo','Monto'] as $i=>$h) { $c=chr(65+$i); $sheet->setCellValue($c.$row, $h); $sheet->getStyle($c.$row)->applyFromArray($styles['header']); } $row++;
        $tExt=0;
        foreach ($extornos as $e) {
            $clienteNombre = $e->creditoDestino?->proposicion?->cliente?->NombresApellidos ?? $e->clienteDestino?->NombresApellidos ?? $e->clienteOrigen?->NombresApellidos ?? 'N/A';
            $codigoCredito = $e->creditoDestino?->proposicion?->CodigoCredito ?? '';
            $ctaCliente = $codigoCredito ? "{$codigoCredito} - ".mb_strtoupper($clienteNombre) : mb_strtoupper($clienteNombre);
            if ($e->TipoResolucion !== 'TRASLADO_DE_PAGO') $tExt += $e->MontoAplicar;
            $tipo = $e->TipoResolucion === 'TRASLADO_DE_PAGO' ? 'TRAS' : 'EXT';
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
    public function carteraExcel(Request $request)
    {
        $fecha = $request->get('fecha');
        $tipos = $request->get('tipos', '');
        if (!$fecha) abort(400, 'Debe especificar una fecha.');

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $hoy = Carbon::today();
        $tiposArray = array_filter(explode(',', $tipos));
        if (empty($tiposArray)) abort(400, 'Debe seleccionar al menos un tipo de cartera.');

        $sedeId = $this->resolveSedeId();

        $query = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->where('ProposicionCredito.SaldoPendiente', '>', 0)
            ->where('ProposicionCredito.FueRefinanciada', 0)
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->whereDate('Credito.FechaGeneracion', '<=', $fechaCarbon)
            ->select(
                'Credito.CreditoID', 'Credito.FechaGeneracion', 'Credito.FechaVencimiento',
                'Credito.ProposicionCreditoID', 'TipoCredito.Descripcion as TipoCreditoDescripcion',
                'Cliente.NombresApellidos', 'ProposicionCredito.MontoTotalPagar',
                'ProposicionCredito.SaldoPendiente', 'ProposicionCredito.CodigoCredito',
                'Zona.Nombre as ZonaNombre'
            )
            ->orderBy('Credito.FechaVencimiento')
            ->get();

        // Pre-agregar pagos en UNA sola query (evita N+1)
        $creditoIds = $query->pluck('CreditoID')->toArray();
        $pagosSums = Pago::withoutGlobalScopes()
            ->whereIn('CreditoID', $creditoIds)
            ->where('Activo', 1)
            ->selectRaw('CreditoID, SUM(MontoPagado) as total_pagado')
            ->groupBy('CreditoID')
            ->pluck('total_pagado', 'CreditoID');

        $titulos = [
            'no_vencida' => 'CARTERA NO VENCIDA',
            'vencida' => 'CARTERA VENCIDA (1-7 días)',
            'morosa' => 'CARTERA MOROSA (8-180 días)',
            'pesada' => 'CARTERA PESADA / PERDIDA (181+ días)',
        ];

        $secciones = [];
        foreach ($tiposArray as $t) {
            $secciones[$t] = ['titulo' => $titulos[$t] ?? $t, 'creditos' => [], 'totalSaldo' => 0];
        }

        foreach ($query as $credito) {
            $fechaVenc = $credito->FechaVencimiento ? Carbon::parse($credito->FechaVencimiento) : null;
            if (!$fechaVenc) continue;

            $diasVencimiento = $hoy->diffInDays($fechaVenc, false);
            $pagado = $pagosSums[$credito->CreditoID] ?? 0;
            $total = (float) $credito->MontoTotalPagar;
            $saldo = max(0, $total - $pagado);
            if ($saldo <= 0) continue;

            $item = [
                'tipo' => $credito->TipoCreditoDescripcion, 'cliente' => $credito->NombresApellidos,
                'zona' => $credito->ZonaNombre ?? '-', 'saldo' => $saldo,
                'fecha_venc' => $fechaVenc->format('d/m/Y'),
                'dias' => abs(intval($hoy->diffInDays($fechaVenc, false))),
            ];

            $cat = match(true) {
                $diasVencimiento >= 0 => 'no_vencida',
                abs($diasVencimiento) <= 7 => 'vencida',
                abs($diasVencimiento) <= 180 => 'morosa',
                default => 'pesada',
            };
            if (in_array($cat, $tiposArray)) {
                $secciones[$cat]['creditos'][] = $item;
                $secciones[$cat]['totalSaldo'] += $saldo;
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cartera');
        $styles = $this->getStyles();
        $row = 1;

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', "REPORTE DE CARTERA");
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $totalGeneral = 0;
        foreach ($secciones as $key => $seccion) {
            if (empty($seccion['creditos'])) continue;

            $sheet->setCellValue('A' . $row, $seccion['titulo'] . ' (' . count($seccion['creditos']) . ' créditos)');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            $headers = ['Tipo', 'Cliente', 'Zona', 'Saldo', 'Vencimiento', 'Días'];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . $row, $h);
                $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
            }
            $row++;

            foreach ($seccion['creditos'] as $item) {
                $this->writeDataRow($sheet, $row, [$item['tipo'], $item['cliente'], $item['zona'], $item['saldo'], $item['fecha_venc'], $item['dias']], 6);
                $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal('right');
                $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal('right');
                $row++;
            }

            $sheet->setCellValue('C' . $row, 'TOTAL:');
            $sheet->setCellValue('D' . $row, $seccion['totalSaldo']);
            $sheet->getStyle('C' . $row . ':D' . $row)->applyFromArray($styles['total']);
            $totalGeneral += $seccion['totalSaldo'];
            $row += 2;
        }

        $sheet->setCellValue('C' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('D' . $row, $totalGeneral);
        $sheet->getStyle('C' . $row . ':D' . $row)->applyFromArray($styles['total']);

        foreach (range('A', 'F') as $col) $sheet->getColumnDimension($col)->setWidth(18);

        return $this->downloadSpreadsheet($spreadsheet, "Reporte_Cartera_{$fecha}");
    }

    // ─── CREDITOS VENCIDOS ───
    public function vencidosExcel(Request $request)
    {
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
                    \Carbon\Carbon::parse($fechaRef)->addDay(), now()
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
