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
        if ($solicitud->Estado !== 'PENDIENTE') {
            throw new \Exception('Esta solicitud ya fue procesada.');
        }

        app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

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
        app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

        // Obtener el pago original del Cliente A
        $pagoOriginal = Pago::find($solicitud->PagoOrigenID);
        if (!$pagoOriginal)
            return;

        $montoAplicar = $solicitud->MontoAplicar ?? $pagoOriginal->MontoPagado;

        // Obtener nombre del cliente origen para comentarios
        $clienteOrigenNombre = $solicitud->clienteOrigen?->NombresApellidos ?? 'Cliente Origen';
        $clienteDestinoNombre = $solicitud->clienteDestino?->NombresApellidos ?? 'Cliente Destino';

        // 1. Marcar el pago original como TRASLADADO
        $pagoOriginal->EstadoTraslado = 'TRASLADADO';
        $pagoOriginal->Comentario = ($pagoOriginal->Comentario ? $pagoOriginal->Comentario . ' | ' : '')
            . "TRASLADADO a {$clienteDestinoNombre} - Solicitud #{$solicitud->SolicitudID}";
        $pagoOriginal->save(); // PagoObserver recalcula SaldoPendiente del crédito origen
        
        // RECALCULAR ESTADO DE LA CUOTA ORIGEN
        if ($pagoOriginal->CuotaID) {
            $this->recalcularEstadoCuota($pagoOriginal->CuotaID);
        }

        // 2. Crear nuevo pago en crédito destino
        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        // Usar la fecha del pago ORIGINAL, no la del día abierto
        $fechaPago = $pagoOriginal->FechaPago ?? ($fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : Carbon::now());

        $nuevoPago = Pago::create([
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => $fechaPago,
            'TipoPago' => $pagoOriginal->TipoPago,
            'TipoConcepto' => 'C',
            'EsMora' => false,
            'EsPagoAutomatico' => true,
            'EsPagoAMayor' => true,
            'PagoOrigenID' => $pagoOriginal->PagoID,
            'Comentario' => "Recibido por traslado de {$clienteOrigenNombre}\nSolicitud #{$solicitud->SolicitudID}.\nMonto: S/ " . number_format($montoAplicar, 2),
            'UsuarioRegistro' => $aprobador->name,
            'Activo' => true,
            'SedeID' => optional(\App\Models\Credito::withoutGlobalScope('sede')->find($solicitud->CreditoDestinoID))->SedeID
                ?? $solicitud->SedeID
                ?? $aprobador->SedeID,
            'SolicitudResolucionID' => $solicitud->SolicitudID,
        ]);
        
        if ($nuevoPago->CuotaID) {
            $this->recalcularEstadoCuota($nuevoPago->CuotaID);
        }
        $this->verificarCreditoCancelado($nuevoPago->CreditoID);
    }

    /**
     * Procesar flujo de excedente: resta monto del excedente,
     * aplica al pago existente de la misma fecha o crea pago nuevo en crédito destino si aplica.
     */
    private function procesarExcedente(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        $excedente = $solicitud->excedente;
        if (!$excedente)
            return;

        $montoAplicar = $solicitud->MontoAplicar ?? $excedente->Monto;

        if ($montoAplicar > $excedente->Monto) {
            throw new \Exception(
                "El excedente ya no tiene saldo suficiente. Disponible: S/ "
                . number_format($excedente->Monto, 2)
                . ". Monto solicitado: S/ " . number_format($montoAplicar, 2)
            );
        }

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

        // AHORA SIEMPRE CREAMOS UN PAGO NUEVO INDEPENDIENTE.
        // Esto garantiza que el dinero de extornos (Cuenta a Mayor) jamás se mezcle con pagos físicos normales.
        
        // 1. Asegurar que tenemos un CreditoDestinoID para asignar el nuevo pago
        if (!$solicitud->CreditoDestinoID) {
            $clienteID = $solicitud->ClienteDestinoID ?? $solicitud->ClienteOrigenID;
            
            if ($clienteID) {
                // Buscar algún crédito activo del cliente
                $creditoActivo = \App\Models\Credito::whereHas('proposicion', function ($q) use ($clienteID) {
                    $q->where('ClienteID', $clienteID)->where('Activo', 1);
                })
                    ->where('SedeID', $solicitud->SedeID)
                    ->where('Activo', 1)
                    ->first();
                
                if ($creditoActivo) {
                    $solicitud->CreditoDestinoID = $creditoActivo->CreditoID;
                    $solicitud->save();
                    app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);
                }
            }
        }
        
        // 2. Crear el pago nuevo como Cuenta a Mayor
        if ($solicitud->CreditoDestinoID) {
            $this->crearPagoNuevoDesdeExcedente($solicitud, $excedente, $montoAplicar, $aprobador);
        } else {
            \Log::warning("No se encontró un CreditoDestinoID activo para aplicar la resolución de excedente #{$solicitud->SolicitudID}");
        }
    }

    /**
     * Crea un nuevo pago en el crédito destino a partir de un excedente.
     */
    private function crearPagoNuevoDesdeExcedente(
        SolicitudResolucionExcedente $solicitud,
        $excedente,
        float $montoAplicar,
        $aprobador
    ): void {
        app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

        $tipoPago = 'EFECTIVO';
        if ($excedente->TipoExcedente === 'YAPE_TRANSFERENCIA') {
            $tipoPago = 'TRANSFERENCIA';
        }

        if ($excedente && $excedente->Fecha) {
            $fechaPago = Carbon::parse($excedente->Fecha)->setTime(now()->hour, now()->minute, now()->second);
        } else {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $fechaPago = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : Carbon::now();
        }

        $nuevoPago = Pago::create([
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => $fechaPago,
            'TipoPago' => $tipoPago,
            'TipoConcepto' => 'C',
            'EsMora' => false,
            'EsPagoAutomatico' => true,
            'EsPagoAMayor' => true,
            'Comentario' => "Pago generado por Extorno/Resolución #{$solicitud->SolicitudID}.\nTipo: {$solicitud->TipoResolucion}.\nMonto aplicado: S/ " . number_format($montoAplicar, 2),
            'UsuarioRegistro' => $aprobador->name,
            'Activo' => true,
            'SedeID' => optional(\App\Models\Credito::withoutGlobalScope('sede')->find($solicitud->CreditoDestinoID))->SedeID
                ?? $solicitud->SedeID
                ?? $aprobador->SedeID,
            'SolicitudResolucionID' => $solicitud->SolicitudID,
        ]);

        if ($nuevoPago->CuotaID) {
            $this->recalcularEstadoCuota($nuevoPago->CuotaID);
        }
        $this->verificarCreditoCancelado($nuevoPago->CreditoID);
    }
    
    private function recalcularEstadoCuota($cuotaID): void
    {
        $cuota = Cuota::find($cuotaID);
        if (!$cuota) return;

        $totalPagadoEnCuota = Pago::where('CuotaID', $cuota->CuotaID)
            ->where('Activo', 1)
            ->where(function ($q) {
                $q->whereNull('EstadoTraslado')
                  ->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
            })
            ->sum('MontoPagado');

        $nuevoEstado = $cuota->Estado;
        if ($totalPagadoEnCuota >= $cuota->MontoCuota) {
            $nuevoEstado = 'PAGADA';
        } elseif (now()->isAfter($cuota->FechaVencimiento) && $totalPagadoEnCuota < $cuota->MontoCuota) {
            $nuevoEstado = 'MORA';
        } else {
            $nuevoEstado = 'PENDIENTE';
        }

        $cuota->update(['Estado' => $nuevoEstado]);
    }
    
    private function verificarCreditoCancelado($creditoID): void
    {
        $credito = \App\Models\Credito::find($creditoID);
        if (!$credito) return;

        $montoCuotasTotal = $credito->cuotas()->where('Activo', 1)->sum('MontoCuota');
        $totalPagado = Pago::where('Activo', 1)
            ->where(function ($q) {
                $q->whereNull('EstadoTraslado')->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
            })
            ->whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
            ->sum('MontoPagado');

        if ($totalPagado >= $montoCuotasTotal) {
            $credito->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => now(),
            ]);
        } elseif ($credito->EstatusCreditoFinal === 'SALDADO') {
            $credito->update([
                'EstatusCreditoFinal' => 'ACTIVO',
                'FechaSaldamiento' => null,
            ]);
        }
    }
}
