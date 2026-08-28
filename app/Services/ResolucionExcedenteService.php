<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Pago;
use App\Models\SolicitudResolucionExcedente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResolucionExcedenteService
{
    private const TOLERANCIA = 0.009;

    public static function calcularDistribucion(float $montoAplicar, float $saldoPendiente): array
    {
        $monto = round(max(0, $montoAplicar), 2);
        $saldo = round(max(0, $saldoPendiente), 2);
        $aplicado = min($monto, $saldo);

        return [
            'monto_aplicar' => $monto,
            'saldo_aplicado' => $aplicado,
            'pago_a_mayor' => round(max(0, $monto - $aplicado), 2),
        ];
    }

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
            } elseif ($solicitud->TipoResolucion === 'APLICACION_PAGO_MAYOR') {
                $this->procesarAplicacionPagoMayor($solicitud, $aprobador);
            } elseif ($solicitud->TipoResolucion === 'DEVOLUCION_PAGO_MAYOR') {
                $this->procesarDevolucionPagoMayor($solicitud, $aprobador);
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
        if (! $pagoOriginal) {
            return;
        }

        $montoAplicar = $solicitud->MontoAplicar ?? $pagoOriginal->MontoPagado;

        // Obtener nombre del cliente origen para comentarios
        $clienteOrigenNombre = $solicitud->clienteOrigen?->NombresApellidos ?? 'Cliente Origen';
        $clienteDestinoNombre = $solicitud->clienteDestino?->NombresApellidos ?? 'Cliente Destino';

        // 1. Marcar el pago original como TRASLADADO
        $pagoOriginal->EstadoTraslado = 'TRASLADADO';
        $pagoOriginal->Comentario = ($pagoOriginal->Comentario ? $pagoOriginal->Comentario.' | ' : '')
            ."TRASLADADO a {$clienteDestinoNombre} - Solicitud #{$solicitud->SolicitudID}";
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
            'EsPagoAMayor' => false,
            'PagoOrigenID' => $pagoOriginal->PagoID,
            'Comentario' => "Recibido por traslado de {$clienteOrigenNombre}\nSolicitud #{$solicitud->SolicitudID}.\nMonto: S/ ".number_format($montoAplicar, 2),
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
    }

    /**
     * Aplica un pago a mayor ya existente al saldo de otro credito del mismo
     * cliente. No crea excedente ni movimiento de caja: solo mueve el asiento
     * con trazabilidad y lo registra como una solicitud aprobable.
     */
    private function procesarAplicacionPagoMayor(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

        $pagoOrigen = Pago::lockForUpdate()->find($solicitud->PagoOrigenID);
        $creditoOrigen = \App\Models\Credito::withoutGlobalScope('sede')
            ->with('proposicion')
            ->lockForUpdate()
            ->find($solicitud->CreditoOrigenID);
        $creditoDestino = \App\Models\Credito::withoutGlobalScope('sede')
            ->with('proposicion')
            ->lockForUpdate()
            ->find($solicitud->CreditoDestinoID);

        if (! $pagoOrigen || ! $creditoOrigen || ! $creditoDestino) {
            throw new \Exception('No se encontraron todos los registros del traslado de pago a mayor.');
        }

        if ((int) $creditoOrigen->CreditoID === (int) $creditoDestino->CreditoID) {
            throw new \Exception('El credito destino debe ser diferente al credito origen.');
        }

        if (! $pagoOrigen->EsPagoAMayor || $pagoOrigen->EsPagoAMayorPorMora) {
            throw new \Exception('El pago seleccionado no es un pago a mayor disponible.');
        }

        if (! $pagoOrigen->Activo || in_array($pagoOrigen->EstadoTraslado, ['TRASLADADO', 'DEVUELTO'], true)) {
            throw new \Exception('El pago a mayor seleccionado ya fue trasladado o resuelto.');
        }

        $clienteOrigen = (int) $creditoOrigen->proposicion?->ClienteID;
        $clienteDestino = (int) $creditoDestino->proposicion?->ClienteID;
        if ($clienteOrigen === 0 || $clienteOrigen !== $clienteDestino) {
            throw new \Exception('El pago a mayor solo puede aplicarse a otro credito del mismo cliente.');
        }

        if ((int) $creditoOrigen->SedeID !== (int) $creditoDestino->SedeID) {
            throw new \Exception('El pago a mayor solo puede aplicarse dentro de la misma sede.');
        }

        $montoAplicar = round((float) ($solicitud->MontoAplicar ?? 0), 2);
        $montoDisponible = $this->montoDisponiblePagoMayor($pagoOrigen, $solicitud->SolicitudID);
        $saldoDestino = round((float) ($creditoDestino->proposicion?->SaldoPendiente ?? 0), 2);

        if ($montoAplicar <= 0) {
            throw new \Exception('El monto a aplicar debe ser mayor a 0.');
        }

        if ($montoAplicar > round($montoDisponible, 2)) {
            throw new \Exception('El monto supera el pago a mayor disponible: S/ '.number_format($montoDisponible, 2).'.');
        }

        if ($montoAplicar > $saldoDestino) {
            throw new \Exception('El monto supera el saldo pendiente del credito destino: S/ '.number_format($saldoDestino, 2).'.');
        }

        $fechaPago = $pagoOrigen->FechaPago
            ?? (\App\Services\DateFieldResolver::getFechaAbierta()?->setTime(now()->hour, now()->minute, now()->second) ?: Carbon::now());

        $pagoOrigen->EstadoTraslado = $montoAplicar >= round((float) $pagoOrigen->MontoPagado, 2) - self::TOLERANCIA
            ? 'TRASLADADO'
            : $pagoOrigen->EstadoTraslado;
        $pagoOrigen->Comentario = ($pagoOrigen->Comentario ? $pagoOrigen->Comentario.' | ' : '')
            .'APLICADO A '.$creditoDestino->proposicion->CodigoCredito
            .' - Solicitud #'.$solicitud->SolicitudID
            .' - S/ '.number_format($montoAplicar, 2);
        $pagoOrigen->save();

        Pago::create([
            'CreditoID' => $creditoDestino->CreditoID,
            'CuotaID' => null,
            'MontoPagado' => $montoAplicar,
            'FechaPago' => $fechaPago,
            'TipoPago' => $pagoOrigen->TipoPago,
            'TipoConcepto' => 'C',
            'EsMora' => false,
            'EsPagoAutomatico' => true,
            'EsPagoAMayor' => false,
            'EsPagoAMayorPorMora' => false,
            'PagoOrigenID' => $pagoOrigen->PagoID,
            'Comentario' => 'Aplicacion de pago a mayor al credito '.$creditoDestino->proposicion->CodigoCredito
                ." por solicitud #{$solicitud->SolicitudID}. Monto: S/ ".number_format($montoAplicar, 2),
            'UsuarioRegistro' => $aprobador->name,
            'Activo' => true,
            'SedeID' => $creditoDestino->SedeID,
            'SolicitudResolucionID' => $solicitud->SolicitudID,
        ]);
    }

    private function procesarDevolucionPagoMayor(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        app(SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

        $pagoOrigen = Pago::lockForUpdate()->find($solicitud->PagoOrigenID);
        if (! $pagoOrigen) {
            throw new \Exception('El pago a mayor seleccionado ya no existe.');
        }

        if (! $pagoOrigen->EsPagoAMayor) {
            throw new \Exception('El pago seleccionado no es un pago a mayor.');
        }

        if ($pagoOrigen->EstadoTraslado === 'DEVUELTO') {
            throw new \Exception('El pago a mayor seleccionado ya fue resuelto.');
        }

        $montoAplicar = (float) ($solicitud->MontoAplicar ?? 0);
        $montoDisponible = $this->montoDisponiblePagoMayor($pagoOrigen, $solicitud->SolicitudID);

        if ($montoAplicar <= 0) {
            throw new \Exception('El monto a devolver debe ser mayor a 0.');
        }

        if (round($montoAplicar, 2) > round($montoDisponible, 2)) {
            throw new \Exception(
                'La devolucion no puede superar el monto disponible del pago a mayor: S/ '
                .number_format($montoDisponible, 2)
            );
        }

        $credito = \App\Models\Credito::withoutGlobalScope('sede')
            ->with('proposicion')
            ->find($solicitud->CreditoDestinoID);

        app(\App\Services\FondoSedeService::class)->registrarEgresoDevolucionEfectivo(
            $solicitud->SedeID,
            $montoAplicar,
            $solicitud->SolicitudID,
            $solicitud->CreditoDestinoID,
            $aprobador->id,
            'Devolucion en efectivo de pago a mayor #'
                .$pagoOrigen->PagoID
                .' a '
                .($solicitud->clienteDestino?->NombresApellidos ?? 'cliente')
                .' por solicitud #'
                .$solicitud->SolicitudID
                .' por S/ '
                .number_format($montoAplicar, 2)
                .($credito?->proposicion?->CodigoCredito ? ' - credito '.$credito->proposicion->CodigoCredito : '')
                .($solicitud->DatosValeCaja ? ' - vale: '.$solicitud->DatosValeCaja : '')
        );

        $nuevoDisponible = $montoDisponible - $montoAplicar;
        if ($nuevoDisponible <= 0.009) {
            $pagoOrigen->EstadoTraslado = 'DEVUELTO';
        }

        $pagoOrigen->Comentario = ($pagoOrigen->Comentario ? $pagoOrigen->Comentario.' | ' : '')
            .'DEVOLUCION EFECTIVO A MAYOR S/ '.number_format($montoAplicar, 2)." - Solicitud #{$solicitud->SolicitudID}";
        $pagoOrigen->save();
    }

    private function montoDisponiblePagoMayor(Pago $pagoOrigen, ?int $solicitudActualId = null): float
    {
        $query = SolicitudResolucionExcedente::whereIn('TipoResolucion', ['DEVOLUCION_PAGO_MAYOR', 'APLICACION_PAGO_MAYOR'])
            ->where('PagoOrigenID', $pagoOrigen->PagoID)
            ->where('Estado', '!=', 'RECHAZADA');

        if ($solicitudActualId) {
            $query->where('SolicitudID', '!=', $solicitudActualId);
        }

        $montoComprometido = (float) $query->sum('MontoAplicar');

        return max(0, (float) $pagoOrigen->MontoPagado - $montoComprometido);
    }

    /**
     * Procesar flujo de excedente: resta monto del excedente,
     * aplica al pago existente de la misma fecha o crea pago nuevo en crédito destino si aplica.
     */
    private function procesarExcedente(SolicitudResolucionExcedente $solicitud, $aprobador): void
    {
        $excedente = $solicitud->excedente;
        if (! $excedente) {
            return;
        }

        $montoAplicar = $solicitud->MontoAplicar ?? $excedente->Monto;

        if ($montoAplicar > $excedente->Monto) {
            throw new \Exception(
                'El excedente ya no tiene saldo suficiente. Disponible: S/ '
                .number_format($excedente->Monto, 2)
                .'. Monto solicitado: S/ '.number_format($montoAplicar, 2)
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

        if ($solicitud->TipoResolucion === 'DEVOLUCION_EFECTIVO') {
            if (! $solicitud->CreditoDestinoID) {
                throw new \Exception('Debe seleccionar el credito asociado a la devolucion en efectivo.');
            }

            $credito = \App\Models\Credito::withoutGlobalScope('sede')
                ->with('proposicion')
                ->find($solicitud->CreditoDestinoID);

            app(\App\Services\FondoSedeService::class)->registrarEgresoDevolucionEfectivo(
                $solicitud->SedeID,
                $montoAplicar,
                $solicitud->SolicitudID,
                $solicitud->CreditoDestinoID,
                $aprobador->id,
                'Devolucion en efectivo a '
                    .($solicitud->clienteDestino?->NombresApellidos ?? 'cliente')
                    .' por solicitud #'
                    .$solicitud->SolicitudID
                    .($credito?->proposicion?->CodigoCredito ? ' - credito '.$credito->proposicion->CodigoCredito : '')
                    .($solicitud->DatosValeCaja ? ' - vale: '.$solicitud->DatosValeCaja : '')
            );

            return;
        }

        // AHORA SIEMPRE CREAMOS UN PAGO NUEVO INDEPENDIENTE.
        // Esto garantiza que el dinero de extornos (Cuenta a Mayor) jamás se mezcle con pagos físicos normales.

        // 1. Asegurar que tenemos un CreditoDestinoID para asignar el nuevo pago
        if (! $solicitud->CreditoDestinoID) {
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

        // 2. Distribuir el importe entre deuda y pago a mayor.
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

        $credito = \App\Models\Credito::withoutGlobalScope('sede')
            ->where('CreditoID', $solicitud->CreditoDestinoID)
            ->lockForUpdate()
            ->firstOrFail();
        $proposicion = \App\Models\ProposicionCredito::withoutGlobalScope('sede')
            ->where('ProposicionCreditoID', $credito->ProposicionCreditoID)
            ->lockForUpdate()
            ->firstOrFail();
        $distribucion = self::calcularDistribucion($montoAplicar, (float) $proposicion->SaldoPendiente);

        $datosBase = [
            'CreditoID' => $solicitud->CreditoDestinoID,
            'CuotaID' => null,
            'FechaPago' => $fechaPago,
            'TipoPago' => $tipoPago,
            'TipoConcepto' => 'C',
            'EsMora' => false,
            'EsPagoAutomatico' => true,
            'UsuarioRegistro' => $aprobador->name,
            'Activo' => true,
            'SedeID' => $credito->SedeID ?? $solicitud->SedeID ?? $aprobador->SedeID,
            'SolicitudResolucionID' => $solicitud->SolicitudID,
        ];

        if ($distribucion['saldo_aplicado'] > self::TOLERANCIA) {
            Pago::create($datosBase + [
                'MontoPagado' => $distribucion['saldo_aplicado'],
                'EsPagoAMayor' => false,
                'EsPagoAMayorPorMora' => false,
                'Comentario' => "Pago generado por Extorno/Resolución #{$solicitud->SolicitudID}.\n"
                    ."Tipo: {$solicitud->TipoResolucion}.\n"
                    .'Aplicado a deuda: S/ '.number_format($distribucion['saldo_aplicado'], 2),
            ]);
        }

        if ($distribucion['pago_a_mayor'] > self::TOLERANCIA) {
            Pago::create($datosBase + [
                'MontoPagado' => $distribucion['pago_a_mayor'],
                'EsPagoAMayor' => true,
                'EsPagoAMayorPorMora' => false,
                'Comentario' => "Pago a mayor generado por Extorno/Resolución #{$solicitud->SolicitudID}.\n"
                    ."Tipo: {$solicitud->TipoResolucion}.\n"
                    .'Exceso no aplicado a deuda: S/ '.number_format($distribucion['pago_a_mayor'], 2),
            ]);
        }
    }

    private function recalcularEstadoCuota($cuotaID): void
    {
        $cuota = Cuota::find($cuotaID);
        if (! $cuota) {
            return;
        }

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
}
