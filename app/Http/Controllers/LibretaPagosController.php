<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Exports\LibretaPagosExport;

use Barryvdh\DomPDF\Facade\Pdf;

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


    public function descargarPdf($creditoId)
    {
        $credito = Credito::with(['proposicion.cliente', 'proposicion.tasa', 'cuotas', 'pagos'])
            ->findOrFail($creditoId);

        $proposicion = $credito->proposicion;
        $cliente = $proposicion->cliente;
        $zona = $proposicion->zona->Nombre ?? 'N/A';
        $cuotas = $credito->cuotas()->orderBy('FechaVencimiento')->get();

        $pagosData = [];
        foreach ($credito->pagos as $pago) {
            $pagosData[$pago->CuotaID] = ($pagosData[$pago->CuotaID] ?? 0) + $pago->MontoPagado;
        }

        $nombreDescarga = 'Libreta_' . str_replace(' ', '_', $cliente->NombresApellidos) . '.pdf';

        try {
            // Tamaño personalizado en puntos (1mm = 2.83465pt)
            // 300mm = 850.39pt | 140mm = 396.85pt
            $pdf = Pdf::loadView('pdf.libreta-pagos', compact('credito', 'proposicion', 'cliente', 'zona', 'cuotas', 'pagosData'))
                ->setPaper([0, 0, 850.39, 396.85], 'portrait');

            return $pdf->stream($nombreDescarga);

        } catch (\Exception $e) {
            return "Error al generar PDF: " . $e->getMessage();
        }
    }
}


