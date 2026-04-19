<?php

namespace App\Services;

use App\Models\SolicitudResolucionExcedente;
use App\Models\Pago;
use App\Models\Cuota;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResolucionExcedenteService
{
    public function aprobar(SolicitudResolucionExcedente $solicitud, $aprobador)
    {
        DB::transaction(function () use ($solicitud, $aprobador) {
            // Marcar la solicitud como aprobada
            $solicitud->Estado = 'APROBADA';
            $solicitud->UserAprobadorID = $aprobador->id;
            $solicitud->save();

            if ($solicitud->TipoResolucion === 'TRASLADO_DE_PAGO') {
                // ========== FLUJO TRASLADO DE PAGO ==========
                $this->procesarTrasladoPago($solicitud, $aprobador);
            } else {
                // ========== FLUJO EXCEDENTE (otros tipos) ==========
                $this->procesarExcedente($solicitud, $aprobador);
            }
        });
    }

    /**
     * Procesar traslado de pago: marca pago original como TRASLADADO,
     * crea nuevo pago en crédito destino con trazabilidad.
     */
    private function procesarTrasladoPago(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        // Obtener el pago original del Cliente A
        $pagoOriginal = Pago::find($solicitud->PagoOrigenID);
        if (!$pagoOriginal) return;

        $montoAplicar = $solicitud->MontoAplicar ?? $pagoOriginal->MontoPagado;

        // Obtener nombre del cliente origen para comentarios
        $clienteOrigenNombre = $solicitud->clienteOrigen?->NombresApellidos ?? 'Cliente Origen';
        $clienteDestinoNombre = $solicitud->clienteDestino?->NombresApellidos ?? 'Cliente Destino';

        // 1. Marcar el pago original como TRASLADADO
        $pagoOriginal->EstadoTraslado = 'TRASLADADO';
        $pagoOriginal->Comentario = ($pagoOriginal->Comentario ? $pagoOriginal->Comentario . ' | ' : '') 
            . "TRASLADADO a {$clienteDestinoNombre} - Solicitud #{$solicitud->SolicitudID}";
        $pagoOriginal->save(); // PagoObserver recalcula SaldoPendiente del crédito origen

        // 2. Crear nuevo pago en crédito destino
        $cuota = Cuota::where('CreditoID', $solicitud->CreditoDestinoID)
                    ->where('Activo', 1)
                    ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
                    ->orderBy('NumeroCuota', 'asc')
                    ->first();

        Pago::create([
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => $cuota ? $cuota->CuotaID : null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => Carbon::now(),
            'TipoPago' => $pagoOriginal->TipoPago,
            'TipoConcepto' => 'C',
            'EsMora' => false,
            'EsPagoAutomatico' => true,
            'PagoOrigenID' => $pagoOriginal->PagoID,
            'Comentario' => "Recibido por traslado de {$clienteOrigenNombre} - Solicitud #{$solicitud->SolicitudID}. Monto: S/ " . number_format($montoAplicar, 2),
            'UsuarioRegistro' => $aprobador->name,
            'Activo' => true,
            'SedeID' => $solicitud->SedeID ?? $aprobador->SedeID,
            'SolicitudResolucionID' => $solicitud->SolicitudID,
        ]); // PagoObserver recalcula SaldoPendiente del crédito destino
    }

    /**
     * Procesar flujo de excedente: resta monto del excedente,
     * crea pago en crédito destino si aplica.
     */
    private function procesarExcedente(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        $excedente = $solicitud->excedente;
        if (!$excedente) return;

        $montoAplicar = $solicitud->MontoAplicar ?? $excedente->Monto;

        // Propagar el Cliente Origen identificado en la solicitud al excedente
        if ($solicitud->ClienteOrigenID) {
            $excedente->ClienteOrigenID = $solicitud->ClienteOrigenID;
        }

        // Restar el monto aplicado del excedente
        $nuevoMonto = $excedente->Monto - $montoAplicar;

        if ($nuevoMonto <= 0) {
            $excedente->Monto = 0;
            $excedente->EstadoResolucion = 'RESUELTO';
        } else {
            $excedente->Monto = $nuevoMonto;
        }
        
        $excedente->save();

        // Registrar pago en crédito destino
        if (in_array($solicitud->TipoResolucion, ['ASIGNACION_POR_RECLAMO', 'APLICACION_NUEVO_CREDITO'])) {
            $tipoPago = 'EFECTIVO';
            if ($excedente->TipoExcedente === 'YAPE_TRANSFERENCIA') {
                $tipoPago = 'TRANSFERENCIA'; 
            }

            $cuota = Cuota::where('CreditoID', $solicitud->CreditoDestinoID)
                        ->where('Activo', 1)
                        ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
                        ->orderBy('NumeroCuota', 'asc')
                        ->first();

            Pago::create([
                'CreditoID' => $solicitud->CreditoDestinoID,
                'CuotaID' => $cuota ? $cuota->CuotaID : null,
                'MontoPagado' => $montoAplicar,
                'FechaPago' => Carbon::now(),
                'TipoPago' => $tipoPago,
                'TipoConcepto' => 'C',
                'EsMora' => false,
                'EsPagoAutomatico' => true,
                'Comentario' => "Pago generado por Extorno/Resolución #{$solicitud->SolicitudID}. Tipo: {$solicitud->TipoResolucion}. Monto aplicado: S/ " . number_format($montoAplicar, 2),
                'UsuarioRegistro' => $aprobador->name,
                'Activo' => true,
                'SedeID' => $solicitud->SedeID ?? $aprobador->SedeID,
                'SolicitudResolucionID' => $solicitud->SolicitudID,
            ]);
        }
    }
}
