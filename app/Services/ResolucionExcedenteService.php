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
        $cuota = Cuota::where('CreditoID', $solicitud->CreditoDestinoID)
            ->where('Activo', 1)
            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
            ->orderBy('NumeroCuota', 'asc')
            ->first();

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaPago = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : Carbon::now();

        $nuevoPago = Pago::create([
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => $cuota ? $cuota->CuotaID : null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => $fechaPago,
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

        // Para ASIGNACION_POR_RECLAMO y DEVOLUCION_EFECTIVO:
        // Buscar pago existente en la misma fecha del excedente y aplicar el monto al pago original.
        if (in_array($solicitud->TipoResolucion, ['ASIGNACION_POR_RECLAMO', 'DEVOLUCION_EFECTIVO'])) {
            $fechaExcedente = $excedente->Fecha;

            // Buscar un pago existente en la misma fecha del excedente
            $pagoExistente = null;
            if ($solicitud->CreditoDestinoID) {
                // Buscar en el crédito destino específico
                $pagoExistente = Pago::where('CreditoID', $solicitud->CreditoDestinoID)
                    ->where('Activo', 1)
                    ->where(function ($q) {
                        $q->whereNull('EstadoTraslado')
                          ->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
                    })
                    ->whereDate('FechaPago', $fechaExcedente)
                    ->orderBy('PagoID', 'asc')
                    ->first();
            } elseif ($solicitud->ClienteDestinoID) {
                // Para DEVOLUCION_EFECTIVO: buscar en todos los créditos activos del cliente destino
                $creditosIDs = \App\Models\Credito::whereHas('proposicion', function ($q) use ($solicitud) {
                    $q->where('ClienteID', $solicitud->ClienteDestinoID)->where('Activo', 1);
                })->where('Activo', 1)->pluck('CreditoID');

                if ($creditosIDs->isNotEmpty()) {
                    $pagoExistente = Pago::whereIn('CreditoID', $creditosIDs)
                        ->where('Activo', 1)
                        ->where(function ($q) {
                            $q->whereNull('EstadoTraslado')
                              ->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
                        })
                        ->whereDate('FechaPago', $fechaExcedente)
                        ->orderBy('PagoID', 'asc')
                        ->first();
                }
            }

            if ($pagoExistente) {
                // Aplicar el excedente al pago existente de la misma fecha
                $montoOriginal = $pagoExistente->MontoPagado;
                $pagoExistente->MontoPagado = $montoOriginal + $montoAplicar;
                $pagoExistente->Comentario = ($pagoExistente->Comentario ? $pagoExistente->Comentario . ' | ' : '')
                    . "Excedente aplicado: +S/ " . number_format($montoAplicar, 2)
                    . " (Resolución #{$solicitud->SolicitudID}). Pago original: S/ " . number_format($montoOriginal, 2)
                    . ", nuevo total: S/ " . number_format($montoOriginal + $montoAplicar, 2);
                $pagoExistente->save();

                // Recalcular estado de la cuota afectada
                if ($pagoExistente->CuotaID) {
                    $this->recalcularEstadoCuota($pagoExistente->CuotaID);
                }
                $this->verificarCreditoCancelado($pagoExistente->CreditoID);
            } else {
                // No se encontró pago en esa fecha: crear pago nuevo (fallback)
                $this->crearPagoNuevoDesdeExcedente($solicitud, $excedente, $montoAplicar, $aprobador);
            }
        } elseif ($solicitud->TipoResolucion === 'APLICACION_NUEVO_CREDITO') {
            // Para APLICACION_NUEVO_CREDITO siempre crear pago nuevo
            $this->crearPagoNuevoDesdeExcedente($solicitud, $excedente, $montoAplicar, $aprobador);
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
        $tipoPago = 'EFECTIVO';
        if ($excedente->TipoExcedente === 'YAPE_TRANSFERENCIA') {
            $tipoPago = 'TRANSFERENCIA';
        }

        $cuota = Cuota::where('CreditoID', $solicitud->CreditoDestinoID)
            ->where('Activo', 1)
            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
            ->orderBy('NumeroCuota', 'asc')
            ->first();

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaPago = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : Carbon::now();

        $nuevoPago = Pago::create([
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => $cuota ? $cuota->CuotaID : null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => $fechaPago,
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
            $credito->update(['Estado' => 'CANCELADO']);
        } else {
            // Revertir a NORMAL si estaba CANCELADO pero ya no lo está
            if ($credito->Estado === 'CANCELADO') {
                $credito->update(['Estado' => 'NORMAL']);
            }
        }
    }
}
