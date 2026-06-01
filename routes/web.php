<?php

use Illuminate\Support\Facades\Route;
use App\Models\ProposicionCredito;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::middleware(['auth', 'throttle:api'])->group(function () {
    Route::get('/pdf/acta-creditos', function () {
        $fecha = request()->get('fecha') ? \Carbon\Carbon::createFromFormat('Y-m-d', request()->get('fecha')) : now();

        $sedeId = auth()->user()->getEffectiveSedeId();

        $proposiciones = ProposicionCredito::with(['cliente', 'zona', 'tipoCredito', 'tasa'])
            ->where('Activo', true)
            ->whereDate('FechaPropuesta', '=', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->orderBy('CodigoCredito')
            ->get();

        $pdf = Pdf::loadView('pdf.acta-creditos', [
            'proposiciones' => $proposiciones,
            'fecha' => $fecha->format('d/m/Y'),
        ]);

        $pdf->setPaper('a3', 'landscape');

        return $pdf->stream('Acta_Creditos_' . now()->format('Y-m-d_His') . '.pdf');
    })->name('acta-creditos.view');

    Route::get('/excel/acta-creditos', [\App\Http\Controllers\DescargarActaExcelController::class, 'descargar'])
        ->name('acta-creditos.excel');

    Route::get('/pdf/clientes-inactivos', function () {
        $nombre = request()->get('nombre');
        $fechaDesde = request()->get('fecha_desde');
        $fechaHasta = request()->get('fecha_hasta');

        $sedeParam = request()->get('sede_id');
        $sedeId = ($sedeParam === '0' || $sedeParam === 'todas' || $sedeParam === '')
            ? null
            : (($sedeParam) ? (int) $sedeParam : auth()->user()->getEffectiveSedeId());

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
                  ->where('p2.Activo', true)
                  ->where('c2.Activo', true)
                  ->where('c2.EstatusCreditoFinal', '!=', 'SALDADO');
            });

        if ($nombre) {
            $clientes->where(function ($q) use ($nombre) {
                $q->where('Cliente.NombresApellidos', 'like', "%{$nombre}%")
                  ->orWhere('Cliente.DNI', 'like', "%{$nombre}%");
            });
        }
        if ($fechaDesde) {
            $clientes->havingRaw('MAX(Credito.FechaSaldamiento) >= ?', [$fechaDesde]);
        }
        if ($fechaHasta) {
            $clientes->havingRaw('MAX(Credito.FechaSaldamiento) <= ?', [$fechaHasta]);
        }

        $clientes = $clientes->groupBy('Cliente.ClienteID', 'Cliente.DNI', 'Cliente.NombresApellidos')
            ->havingRaw('dias_inactivo >= 1')
            ->orderByRaw('dias_inactivo DESC')
            ->get();

        $pdf = Pdf::loadView('reportes.clientes-inactivos', [
            'clientes' => $clientes,
            'fecha' => now()->format('d/m/Y'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Clientes_Inactivos_' . now()->format('d-m-Y') . '.pdf');
    })->name('clientes-inactivos.view');

    Route::get('/pdf/clientes-atraso', function () {
        $fechaDesde = request()->get('fecha_desde');
        $fechaHasta = request()->get('fecha_hasta');
        $clienteId = request()->get('cliente_id');
        $sedeParam = request()->get('sede_id');
        $sedeId = ($sedeParam === '0' || $sedeParam === 'todas' || $sedeParam === '')
            ? null
            : (($sedeParam) ? (int) $sedeParam : auth()->user()->getEffectiveSedeId());

        $query = \App\Models\Credito::where('Activo', 1)
            ->whereHas('proposicion', function ($q) {
                $q->where('SaldoPendiente', '>', 0);
            })
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->select('Credito.*')
            ->selectRaw("DATEDIFF(NOW(), COALESCE((SELECT MAX(FechaPago) FROM pago WHERE pago.CreditoID = Credito.CreditoID AND pago.Activo = 1), FechaGeneracion)) as dias_atraso_calc")
            ->havingRaw('dias_atraso_calc >= 1');

        if ($clienteId) {
            $query->whereHas('proposicion', fn($q) => $q->where('ClienteID', $clienteId));
        }
        if ($fechaDesde) {
            $query->whereDate('FechaVencimiento', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->whereDate('FechaVencimiento', '<=', $fechaHasta);
        }

        $creditos = $query->with(['proposicion.cliente', 'proposicion.zona'])
            ->orderByRaw('dias_atraso_calc DESC')
            ->get();

        $pdf = Pdf::loadView('reportes.clientes-atraso', [
            'creditos' => $creditos,
            'fecha' => now()->format('d/m/Y'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Clientes_Atraso_' . now()->format('d-m-Y') . '.pdf');
    })->name('clientes-atraso.view');

    Route::get('/pdf/creditos-vencidos', function () {
        $fechaDesde = request()->get('fecha_desde') ?? request()->get('fecha');
        $fechaHasta = request()->get('fecha_hasta') ?? request()->get('fecha');
        $sedeParam = request()->get('sede_id');
        $clienteId = request()->get('cliente_id');
        $tipoCreditoId = request()->get('tipo_credito_id');

        $fechaCarbonDesde = $fechaDesde ? \Carbon\Carbon::createFromFormat('Y-m-d', $fechaDesde) : now();
        $fechaCarbonHasta = $fechaHasta ? \Carbon\Carbon::createFromFormat('Y-m-d', $fechaHasta) : $fechaCarbonDesde;
        
        $sedeId = ($sedeParam === '0' || $sedeParam === 'todas' || $sedeParam === '')
            ? null
            : (($sedeParam) ? (int) $sedeParam : auth()->user()->getEffectiveSedeId());

        $query = \App\Models\Credito::where('Activo', 1)
            ->whereHas('proposicion', function ($q) use ($clienteId, $tipoCreditoId) {
                $q->where('SaldoPendiente', '>', 0);
                if ($clienteId) {
                    $q->where('ClienteID', $clienteId);
                }
                if ($tipoCreditoId) {
                    $q->where('TipoCreditoID', $tipoCreditoId);
                }
            })
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->with(['proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.zona']);

        if ($fechaDesde) {
            $query->whereDate('FechaVencimiento', '>=', $fechaCarbonDesde->toDateString());
        }
        if ($fechaHasta) {
            $query->whereDate('FechaVencimiento', '<=', $fechaCarbonHasta->toDateString());
        }

        $creditos = $query->orderBy('FechaVencimiento', 'asc')->get();

        $titulo = $fechaCarbonDesde->format('d/m/Y');
        if ($fechaDesde !== $fechaHasta) {
            $titulo .= ' - ' . $fechaCarbonHasta->format('d/m/Y');
        }

        $sedeNombre = '';
        if ($sedeId) {
            $sede = \App\Models\Sede::find($sedeId);
            $sedeNombre = $sede ? $sede->Nombre : '';
        }

        $pdf = Pdf::loadView('reportes.creditos-vencidos', [
            'creditos' => $creditos,
            'fecha' => $titulo,
            'sedeNombre' => $sedeNombre,
            'sedeId' => $sedeId,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $nombreArchivo = 'Creditos_Vencidos_' . $fechaCarbonDesde->format('d-m-Y');
        if ($fechaDesde !== $fechaHasta) {
            $nombreArchivo .= '_al_' . $fechaCarbonHasta->format('d-m-Y');
        }

        return $pdf->stream($nombreArchivo . '.pdf');
    })->name('creditos-vencidos.view');

    Route::get('/pdf/cuentas-canceladas', function () {
        $fecha = request()->get('fecha') ? \Carbon\Carbon::createFromFormat('Y-m-d', request()->get('fecha')) : now();
        $sedeParam = request()->get('sede_id');
        $sedeId = ($sedeParam === '0' || $sedeParam === 'todas' || $sedeParam === '')
            ? null
            : (($sedeParam) ? (int) $sedeParam : auth()->user()->getEffectiveSedeId());

        $proposiciones = \App\Models\ProposicionCredito::select('ProposicionCredito.*')
            ->join('Credito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('ProposicionCredito.SaldoPendiente', 0)
            ->whereDate('Credito.FechaSaldamiento', '=', $fecha)
            ->when($sedeId, fn($q) => $q->where('ProposicionCredito.SedeID', $sedeId))
            ->with(['cliente', 'credito', 'zona'])
            ->orderByDesc('Credito.FechaSaldamiento')
            ->get();

        $pdf = Pdf::loadView('reportes.cuentas-canceladas', [
            'proposiciones' => $proposiciones,
            'fecha' => $fecha->format('d/m/Y'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Cuentas_Canceladas_' . $fecha->format('d-m-Y') . '.pdf');
    })->name('cuentas-canceladas.view');

    Route::get('/reportes/compras/excel', [App\Http\Controllers\ComprasReporteController::class, 'descargarExcel'])
        ->name('compras.excel');

    Route::get('/reportes/compras/pdf', [App\Http\Controllers\ComprasReporteController::class, 'descargarPdf'])
        ->name('compras.pdf');

    Route::get('/reportes/gastos/excel', [App\Http\Controllers\GastoReporteController::class, 'descargarExcel'])
        ->name('gastos.excel');

    Route::get('/reportes/gastos/pdf', [App\Http\Controllers\GastoReporteController::class, 'descargarPdf'])
        ->name('gastos.pdf');

    Route::get('/libreta-pagos/{credito}', [App\Http\Controllers\LibretaPagosController::class, 'descargar'])
        ->name('libreta-pagos.descargar');

    Route::get('/libreta-pagos/{credito}/pdf', [App\Http\Controllers\LibretaPagosController::class, 'descargarPdf'])
        ->name('libreta-pagos.descargar-pdf');

    Route::get('/libreta-pagos/{credito}/html', [App\Http\Controllers\LibretaPagosController::class, 'descargarPdf'])
        ->name('libreta-pagos.html');

    Route::get('/ticket/{credito}', [App\Http\Controllers\TicketDescargarController::class, 'descargar'])
        ->name('ticket.descargar');

    Route::get('/pdf/reporte-diario', [App\Http\Controllers\ReporteDiarioController::class, 'descargar'])
        ->name('reporte-diario.pdf');

    Route::get('/descargar-pagos/{credito}', [App\Http\Controllers\DescargarPagosController::class, 'descargar'])
        ->name('descargar-pagos.pdf');

    Route::get('/pdf/reporte-cartera', [App\Http\Controllers\ReporteCarteraController::class, 'descargar'])
        ->name('reporte-cartera.pdf');

    // ─── Reportes Excel para Gerencia ───
    Route::get('/reportes/diario/excel', [App\Http\Controllers\ReporteExportController::class, 'diarioExcel'])
        ->name('reporte-diario.excel');
    Route::get('/reportes/canceladas/excel', [App\Http\Controllers\ReporteExportController::class, 'canceladasExcel'])
        ->name('reporte-canceladas.excel');
    Route::get('/reportes/cartera/excel', [App\Http\Controllers\ReporteExportController::class, 'carteraExcel'])
        ->name('reporte-cartera.excel');
    Route::get('/reportes/vencidos/excel', [App\Http\Controllers\ReporteExportController::class, 'vencidosExcel'])
        ->name('reporte-vencidos.excel');
    Route::get('/reportes/atraso/excel', [App\Http\Controllers\ReporteExportController::class, 'atrasoExcel'])
        ->name('reporte-atraso.excel');
    Route::get('/reportes/inactivos/excel', [App\Http\Controllers\ReporteExportController::class, 'inactivosExcel'])
        ->name('reporte-inactivos.excel');

    Route::get('/reportes/clientes/excel', [App\Http\Controllers\ClienteReporteController::class, 'descargarExcel'])
        ->name('clientes.excel');
    Route::get('/reportes/clientes/pdf', [App\Http\Controllers\ClienteReporteController::class, 'descargarPdf'])
        ->name('clientes.pdf');
});