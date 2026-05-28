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
        if (!$fecha) {
            abort(400, 'Fecha no proporcionada');
        }
        $sedeId = $this->resolveSedeId();
        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $fechaInicioDia = $fechaCarbon->copy()->startOfDay();
        $fechaFinDia = $fechaCarbon->copy()->endOfDay();

        $sedeNombre = $sedeId ? (Sede::find($sedeId)?->Nombre ?? 'SEDE NO ESPECIFICADA') : 'TODAS LAS SEDES';

        // Gastos
        $gastos = Gasto::withoutGlobalScopes()
            ->where('Activo', true)->whereDate('FechaEmision', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with('proveedor', 'motivo', 'detalles')->orderBy('GastoID')->get();

        // Compras
        $compras = Compra::withoutGlobalScopes()
            ->where('Activo', true)->whereDate('FechaEmision', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with('proveedor', 'detalles')->orderBy('CompraID')->get();

        // Ingresos Remesas
        $ingresosRemesas = TransferenciaSede::withoutGlobalScopes()
            ->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                      $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                  });
            })
            ->when($sedeId, fn($q) => $q->where('SedeDestinoID', $sedeId))
            ->get();

        // Salidas Remesas
        $salidasRemesas = TransferenciaSede::withoutGlobalScopes()
            ->where('Estado', 'ACEPTADO')
            ->where(function($q) use ($fechaInicioDia, $fechaFinDia) {
                $q->whereBetween('FechaRespuesta', [$fechaInicioDia, $fechaFinDia])
                  ->orWhere(function($q2) use ($fechaInicioDia, $fechaFinDia) {
                      $q2->whereNull('FechaRespuesta')->whereBetween('FechaTransferencia', [$fechaInicioDia, $fechaFinDia]);
                  });
            })
            ->when($sedeId, fn($q) => $q->where('SedeOrigenID', $sedeId))
            ->get();

        // Amortizaciones
        $pagos = Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)->where('pago.EsPagoAMayor', false)
            ->whereDate('pago.FechaPago', $fecha)
            ->when($sedeId, fn($q) => $q->where('pago.SedeID', $sedeId))
            ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->select('ProposicionCredito.CodigoCredito', 'Cliente.NombresApellidos', 'pago.MontoPagado')
            ->orderBy('pago.PagoID')->get();

        // Créditos emitidos
        $creditos = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', true)->whereDate('Credito.FechaGeneracion', $fecha)
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->select('ProposicionCredito.CodigoCredito', 'Cliente.NombresApellidos', 'ProposicionCredito.MontoTotal', 'ProposicionCredito.MontoInteres', 'ProposicionCredito.MontoTotalPagar')
            ->orderBy('Credito.CreditoID')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Balance Diario');
        $styles = $this->getStyles();
        $row = 1;

        // Título
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', "BALANCE DIARIO - {$fecha}");
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $row = 2;
        $sheet->setCellValue('A' . $row, "Sede: {$sedeNombre}");
        $row += 2;

        // Amortizaciones
        $sheet->setCellValue('A' . $row, 'AMORTIZACIONES');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $headers = ['Operación', 'Cliente', 'Monto'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalAmort = 0;
        foreach ($pagos as $p) {
            $this->writeDataRow($sheet, $row, [$p->CodigoCredito, $p->NombresApellidos, $p->MontoPagado], 3);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal('right');
            $totalAmort += $p->MontoPagado;
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTAL AMORTIZACIONES:');
        $sheet->setCellValue('C' . $row, $totalAmort);
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($styles['total']);
        $row += 2;

        // Créditos Emitidos
        $sheet->setCellValue('A' . $row, 'CREDITOS EMITIDOS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $headers = ['Operación', 'Cliente', 'Capital', 'Interés', 'Total'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalCap = 0; $totalInt = 0; $totalPag = 0;
        foreach ($creditos as $c) {
            $this->writeDataRow($sheet, $row, [$c->CodigoCredito, $c->NombresApellidos, $c->MontoTotal, $c->MontoInteres, $c->MontoTotalPagar], 5);
            foreach (['C', 'D', 'E'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal('right');
            }
            $totalCap += $c->MontoTotal; $totalInt += $c->MontoInteres; $totalPag += $c->MontoTotalPagar;
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTAL CREDITOS:');
        $sheet->setCellValue('C' . $row, $totalCap);
        $sheet->setCellValue('D' . $row, $totalInt);
        $sheet->setCellValue('E' . $row, $totalPag);
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styles['total']);
        $row += 2;

        // Ingresos Remesas
        $sheet->setCellValue('A' . $row, 'INGRESO DE REMESAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $headers = ['Origen', 'Monto', 'Fecha'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalIng = 0;
        foreach ($ingresosRemesas as $t) {
            $this->writeDataRow($sheet, $row, [$t->sedeOrigen?->Nombre, $t->Monto, $t->FechaRespuesta?->format('d/m/Y')], 3);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal('right');
            $totalIng += $t->Monto;
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTAL INGRESOS:');
        $sheet->setCellValue('B' . $row, $totalIng);
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($styles['total']);
        $row += 2;

        // Gastos
        $sheet->setCellValue('A' . $row, 'CAJA CHICA - GASTOS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $headers = ['Comprobante', 'Proveedor', 'Motivo', 'Total'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalGastos = 0;
        foreach ($gastos as $g) {
            $this->writeDataRow($sheet, $row, [$g->tipoComprobanteGasto?->Nombre, $g->proveedor?->Nombre, $g->motivo?->Nombre, $g->Total], 4);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal('right');
            $totalGastos += $g->Total;
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTAL GASTOS:');
        $sheet->setCellValue('D' . $row, $totalGastos);
        $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($styles['total']);
        $row += 2;

        // Compras
        $sheet->setCellValue('A' . $row, 'CAJA CHICA - COMPRAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $headers = ['Comprobante', 'Proveedor', 'Total'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;
        $totalCompras = 0;
        foreach ($compras as $c) {
            $this->writeDataRow($sheet, $row, [$c->tipoComprobante?->Nombre, $c->proveedor?->Nombre, $c->Total], 3);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal('right');
            $totalCompras += $c->Total;
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTAL COMPRAS:');
        $sheet->setCellValue('C' . $row, $totalCompras);
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($styles['total']);

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

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

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', "CUENTAS CANCELADAS - {$fecha->format('d/m/Y')}");
        $sheet->getStyle('A1')->applyFromArray($styles['title']);
        $row = 3;

        $headers = ['Operación', 'Cliente', 'Zona', 'Cuenta', 'Total', 'F. Saldado', 'Vencimiento'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
            $sheet->getStyle(chr(65 + $i) . $row)->applyFromArray($styles['header']);
        }
        $row++;

        $total = 0;
        foreach ($proposiciones as $prop) {
            $this->writeDataRow($sheet, $row, [
                str_pad($prop->ProposicionCreditoID, 11, '0', STR_PAD_LEFT),
                $prop->cliente?->NombresApellidos ?? '-',
                $prop->zona?->Nombre ?? '-',
                $prop->CodigoCredito,
                $prop->MontoTotalPagar,
                $prop->credito?->FechaSaldamiento?->format('d/m/Y') ?? '-',
                $prop->credito?->FechaVencimiento?->format('d/m/Y') ?? '-',
            ], 7);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('right');
            $total += $prop->MontoTotalPagar;
            $row++;
        }

        $sheet->setCellValue('D' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('E' . $row, $total);
        $sheet->getStyle('D' . $row . ':E' . $row)->applyFromArray($styles['total']);

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);

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
            $pagado = Pago::withoutGlobalScopes()
                ->whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
                ->where('Activo', 1)->sum('MontoPagado');
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
