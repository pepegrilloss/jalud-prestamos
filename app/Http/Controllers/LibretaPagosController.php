<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Exports\LibretaPagosExport;

class LibretaPagosController extends Controller
{
    public function descargar($creditoId)
    {
        $credito = Credito::with(['proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.tasa'])
            ->findOrFail($creditoId);

        $export = new LibretaPagosExport($credito);
        $fileName = $export->generarExcel();
        $nombreDescarga = 'Libreta_Pagos_' . $credito->proposicion->cliente->NombresApellidos . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->download($fileName, $nombreDescarga, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

