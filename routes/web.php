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

        $proposiciones = ProposicionCredito::with(['cliente', 'zona', 'tipoCredito', 'tasa'])
            ->where('Activo', true)
            ->whereDate('FechaPropuesta', '=', $fecha)
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

    Route::get('/pdf/creditos-vencidos', function () {
        $fechaDesde = request()->get('fecha_desde') ?? request()->get('fecha');
        $fechaHasta = request()->get('fecha_hasta') ?? request()->get('fecha');

        $fechaCarbonDesde = $fechaDesde ? \Carbon\Carbon::createFromFormat('Y-m-d', $fechaDesde) : now();
        $fechaCarbonHasta = $fechaHasta ? \Carbon\Carbon::createFromFormat('Y-m-d', $fechaHasta) : $fechaCarbonDesde;

        $query = \App\Models\Credito::where('Activo', 1)
            ->whereHas('proposicion', function ($q) {
                $q->where('SaldoPendiente', '>', 0);
            })
            ->with(['proposicion.cliente', 'proposicion.tipoCredito']);

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

        $pdf = Pdf::loadView('reportes.creditos-vencidos', [
            'creditos' => $creditos,
            'fecha' => $titulo,
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

        $proposiciones = \App\Models\ProposicionCredito::where('SaldoPendiente', 0)
            ->whereDate('FechaModificacion', '=', $fecha)
            ->with(['cliente', 'credito'])
            ->orderByDesc('FechaModificacion')
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
});