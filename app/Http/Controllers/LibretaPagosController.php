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
        $credito = Credito::with(['proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.tasa', 'proposicion.zona', 'pagos', 'cuotas'])
            ->findOrFail($creditoId);

        $proposicion = $credito->proposicion;
        $cliente = $proposicion->cliente;
        $zona = $proposicion->zona->Nombre ?? 'N/A';

        // Fechas de inicio y fin desde la tabla Credito
        $fechaInicio = $credito->FechaInicio ? \Carbon\Carbon::parse($credito->FechaInicio) : \Carbon\Carbon::parse($credito->FechaGeneracion);
        $fechaVencimiento = $credito->FechaVencimiento ? \Carbon\Carbon::parse($credito->FechaVencimiento) : now();
        
        // Si hay pagos posteriores a la fecha de vencimiento, extendemos el calendario
        $ultimoPago = $credito->pagos->max('FechaPago');
        $fechaFin = $fechaVencimiento;
        if ($ultimoPago && \Carbon\Carbon::parse($ultimoPago)->gt($fechaFin)) {
            $fechaFin = \Carbon\Carbon::parse($ultimoPago);
        }

        // Obtener estados de cuotas (para detectar feriados marcados previamente)
        $cuotasEstados = $credito->cuotas->mapWithKeys(function($c) {
            $fecha = \Carbon\Carbon::parse($c->FechaVencimiento)->format('Y-m-d');
            return [$fecha => $c->Estado];
        });

        $period = new \DatePeriod(
            $fechaInicio->startOfDay(),
            new \DateInterval('P1D'),
            $fechaFin->startOfDay()->addDay()
        );

        $calendario = [];
        foreach ($period as $date) {
            $fechaStr = $date->format('Y-m-d');
            $estadoCuota = $cuotasEstados[$fechaStr] ?? null;
            
            $calendario[$fechaStr] = [
                'fecha' => $date,
                'efectivo' => 0,
                'otros' => 0,
                'total_dia' => 0,
                'es_domingo' => $date->isSunday(),
                'es_feriado' => ($estadoCuota === 'FERIADO'),
            ];
        }

        // Agrupar pagos por fecha exacta (sin importar la cuota asignada)
        foreach ($credito->pagos as $pago) {
            $fechaPagoStr = \Carbon\Carbon::parse($pago->FechaPago)->format('Y-m-d');
            
            if (!isset($calendario[$fechaPagoStr])) {
                // Caso borde: Pago fuera del rango esperado, lo agregamos
                $dateObj = \Carbon\Carbon::parse($pago->FechaPago);
                $calendario[$fechaPagoStr] = [
                    'fecha' => $dateObj,
                    'efectivo' => 0,
                    'otros' => 0,
                    'total_dia' => 0,
                    'es_domingo' => $dateObj->isSunday(),
                    'es_feriado' => false,
                ];
            }

            if (empty($pago->TipoPago) || strtoupper($pago->TipoPago) === 'EFECTIVO') {
                $calendario[$fechaPagoStr]['efectivo'] += $pago->MontoPagado;
            } else {
                $calendario[$fechaPagoStr]['otros'] += $pago->MontoPagado;
            }
            $calendario[$fechaPagoStr]['total_dia'] += $pago->MontoPagado;
        }

        // Ordenar por fecha y convertir a lista
        ksort($calendario);
        $calendario = array_values($calendario);

        $nombreDescarga = 'Libreta_' . str_replace(' ', '_', $cliente->NombresApellidos) . '.pdf';

        try {
            $pdf = Pdf::loadView('pdf.libreta-pagos', compact('credito', 'proposicion', 'cliente', 'zona', 'calendario'))
                ->setPaper([0, 0, 850.39, 396.85], 'portrait');

            return $pdf->stream($nombreDescarga);
        } catch (\Exception $e) {
            return "Error al generar PDF: " . $e->getMessage();
        }
    }
}


