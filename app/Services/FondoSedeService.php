<?php

namespace App\Services;

use App\Models\FondoSede;
use App\Models\MovimientoFondo;
use App\Models\TransferenciaSede;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FondoSedeService
{
    /**
     * Inyectar capital a una sede específica.
     */
    public function inyectarCapital($sedeId, $monto, $usuarioId, $observacion = 'Ingreso de Capital Manual')
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $usuarioId, $observacion) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0]
            );

            $saldoAnterior = $fondo->Saldo;
            $saldoNuevo = $saldoAnterior + $monto;

            // Actualizar Saldo
            $fondo->Saldo = $saldoNuevo;
            $fondo->save();

            // Registrar Movimiento
            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'INGRESO_CAPITAL',
                'Monto' => $monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => $observacion,
            ]);

            return $fondo;
        });
    }

    /**
     * Crear y enviar una transferencia a otra sede.
     */
    public function crearTransferencia($sedeOrigenId, $sedeDestinoId, $monto, $usuarioId, $observacion)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }
        if ($sedeOrigenId == $sedeDestinoId) {
            throw ValidationException::withMessages(['SedeDestinoID' => 'No puedes transferir a la misma sede.']);
        }

        return DB::transaction(function () use ($sedeOrigenId, $sedeDestinoId, $monto, $usuarioId, $observacion) {
            $fondoOrigen = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeOrigenId],
                ['Saldo' => 0]
            );

            if ($fondoOrigen->Saldo < $monto) {
                throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en tu sede para esta transferencia.']);
            }

            // Descontar saldo
            $saldoAnterior = $fondoOrigen->Saldo;
            $saldoNuevo = $saldoAnterior - $monto;
            
            $fondoOrigen->Saldo = $saldoNuevo;
            $fondoOrigen->save();

            // Crear Transferencia
            $transferencia = TransferenciaSede::create([
                'SedeOrigenID' => $sedeOrigenId,
                'SedeDestinoID' => $sedeDestinoId,
                'UsuarioOrigenID' => $usuarioId,
                'Monto' => $monto,
                'Estado' => 'PENDIENTE',
                'Observacion' => $observacion,
            ]);

            // Registrar Movimiento Salida
            MovimientoFondo::create([
                'SedeID' => $sedeOrigenId,
                'Tipo' => 'ENVIO_TRANSFERENCIA',
                'Monto' => -$monto, // Negative cause it left
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Transferencia enviada a Sede Destino ({$sedeDestinoId}): $observacion",
            ]);

            return $transferencia;
        });
    }

    /**
     * Aceptar una transferencia entrante.
     */
    public function aceptarTransferencia(TransferenciaSede $transferencia, $usuarioId)
    {
        if ($transferencia->Estado !== 'PENDIENTE') {
            throw ValidationException::withMessages(['estado' => 'La transferencia ya ha sido procesada.']);
        }

        return DB::transaction(function () use ($transferencia, $usuarioId) {
            $fondoDestino = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeDestinoID],
                ['Saldo' => 0]
            );

            // Sumar saldo
            $saldoAnterior = $fondoDestino->Saldo;
            $saldoNuevo = $saldoAnterior + $transferencia->Monto;
            
            $fondoDestino->Saldo = $saldoNuevo;
            $fondoDestino->save();

            // Actualizar transferencia
            $transferencia->update([
                'Estado' => 'ACEPTADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
            ]);

            // Registrar Movimiento Ingreso
            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeDestinoID,
                'Tipo' => 'RECEPCION_TRANSFERENCIA',
                'Monto' => $transferencia->Monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => 'Transferencia aceptada desde sede ' . $transferencia->SedeOrigenID,
            ]);

            return $transferencia;
        });
    }

    /**
     * Rechazar una transferencia y extornar fondos al emisor.
     */
    public function rechazarTransferencia(TransferenciaSede $transferencia, $usuarioId)
    {
        if ($transferencia->Estado !== 'PENDIENTE') {
            throw ValidationException::withMessages(['estado' => 'La transferencia ya ha sido procesada.']);
        }

        return DB::transaction(function () use ($transferencia, $usuarioId) {
            $fondoOrigen = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeOrigenID],
                ['Saldo' => 0]
            );

            // Revertir descuento
            $saldoAnterior = $fondoOrigen->Saldo;
            $saldoNuevo = $saldoAnterior + $transferencia->Monto;
            
            $fondoOrigen->Saldo = $saldoNuevo;
            $fondoOrigen->save();

            // Actualizar transferencia
            $transferencia->update([
                'Estado' => 'RECHAZADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
            ]);

            // Registrar Movimiento Extorno en Origen
            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeOrigenID,
                'Tipo' => 'RECHAZO_TRANSFERENCIA',
                'Monto' => $transferencia->Monto, // (+) Money returned
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => 'Transferencia devuelta por rechazo de la sede ' . $transferencia->SedeDestinoID,
            ]);

            return $transferencia;
        });
    }
}
