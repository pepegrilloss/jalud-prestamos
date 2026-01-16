<?php

use Illuminate\Support\Facades\Route;
use App\Models\ProposicionCredito;
use App\Http\Controllers\RefinanciamientoController;
use App\Http\Controllers\Api\RefinanciamientoApiController;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/api/refinanciamiento/cuentas-disponibles/{clienteID}', [RefinanciamientoApiController::class, 'obtenerCuentasDisponibles']);

Route::get('/api/refinanciamiento/datos-cuenta/{proposicionCreditoID}', [RefinanciamientoApiController::class, 'obtenerDatosCuenta']);

Route::get('/refinanciamiento/cuentas-disponibles/{clienteID}', [RefinanciamientoController::class, 'obtenerCuentasDisponibles'])
    ->name('refinanciamiento.cuentas-disponibles');

Route::get('/refinanciamiento/datos-cuenta/{proposicionCreditoID}', [RefinanciamientoController::class, 'obtenerDatosCuenta'])
    ->name('refinanciamiento.datos-cuenta');

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

Route::get('/libreta-pagos/{credito}', [App\Http\Controllers\LibretaPagosController::class, 'descargar'])
    ->name('libreta-pagos.descargar');

Route::get('/libreta-pagos/{credito}/pdf', [App\Http\Controllers\LibretaPagosController::class, 'descargarPdf'])
    ->name('libreta-pagos.descargar-pdf');

Route::get('/libreta-pagos/{credito}/html', [App\Http\Controllers\LibretaPagosController::class, 'descargarPdf'])
    ->name('libreta-pagos.html');

Route::get('/ticket/{credito}', [App\Http\Controllers\TicketDescargarController::class, 'descargar'])
    ->name('ticket.descargar');