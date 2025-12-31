<?php

use Illuminate\Support\Facades\Route;
use App\Models\ProposicionCredito;
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/pdf/acta-creditos', function () {
    $proposiciones = ProposicionCredito::with(['cliente', 'zona', 'tipoCredito', 'tasa'])
        ->where('Activo', true)
        ->where('Estado', '<>', 'APROBADO')
        ->orderBy('CodigoCredito')
        ->get();

    $pdf = Pdf::loadView('pdf.acta-creditos', [
        'proposiciones' => $proposiciones,
        'fecha' => now()->format('d/m/Y'),
    ]);

    $pdf->setPaper('a3', 'landscape');

    return $pdf->stream('Acta_Creditos_' . now()->format('Y-m-d_His') . '.pdf');
})->name('acta-creditos.view');

Route::get('/libreta-pagos/{credito}', [App\Http\Controllers\LibretaPagosController::class, 'descargar'])
    ->name('libreta-pagos.descargar');

Route::get('/ticket/{credito}', [App\Http\Controllers\TicketDescargarController::class, 'descargar'])
    ->name('ticket.descargar');