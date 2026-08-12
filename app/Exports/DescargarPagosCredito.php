<?php

namespace App\Exports;

use App\Models\Credito;
use Barryvdh\DomPDF\Facade\Pdf;

class DescargarPagosCredito
{
    public function descargar(Credito $credito)
    {
        $proposicion = $credito->proposicion;
        $cliente = $proposicion->cliente;

        // Obtener pagos activos ordenados por fecha
        $pagos = $credito->pagos()
            ->where('Activo', true)
            ->orderBy('FechaPago')
            ->get();

        // Calcular totales
        $totalPagos = $pagos->sum('MontoPagado');
        $moraPagada = $pagos->where('EsMora', true)->sum('MontoPagado');

        // El saldo pendiente es directamente de la proposición
        $saldoPendiente = $proposicion->SaldoPendiente ?? ($proposicion->MontoTotalPagar - $totalPagos);

        // FE (Fecha Emisión) = Fecha de Generación del Crédito
        $fechaEmision = $credito->FechaGeneracion;

        // FV (Fecha Vencimiento) = Fecha de vencimiento de la última cuota
        $fechaVencimiento = $credito->FechaVencimiento ?? $credito->FechaGeneracion;

        // Asegurar UTF-8 en todos los datos
        $data = [
            'numero_operacion' => utf8_encode($proposicion->CodigoCredito ?? ''),
            'emision' => $fechaEmision,
            'vencimiento' => $fechaVencimiento,
            'tipo_credito' => utf8_encode($proposicion->tipoCredito->Descripcion ?? 'N/A'),
            'cliente_id' => utf8_encode($cliente->DNI ?? ''),
            'cliente_nombre' => utf8_encode($cliente->NombresApellidos ?? ''),
            'monto_credito' => (float) $proposicion->MontoTotal,
            'monto_total' => (float) ($proposicion->MontoTotalPagar ?? ($proposicion->MontoTotal + $proposicion->MontoInteres)),
            'monto_mora' => (float) ($proposicion->TasaMora ?? 0),
            'pagos' => $pagos,
            'total_pagos' => (float) $totalPagos,
            'inicial' => 0,
            'total_pagado' => (float) $totalPagos,
            'mora_pagada' => (float) $moraPagada,
            'total_deuda' => (float) $saldoPendiente,
        ];

        $pdf = Pdf::loadView('exports.pagos-credito-pdf', $data);

        return $pdf->stream('pagos-credito-'.$proposicion->CodigoCredito.'.pdf');
    }
}
