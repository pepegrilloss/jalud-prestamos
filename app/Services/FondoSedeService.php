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
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
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
     * Inyectar capital a la Caja Chica de una sede.
     */
    public function inyectarCapitalCajaChica($sedeId, $monto, $usuarioId, $observacion = 'Ingreso de Capital a Caja Chica')
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $usuarioId, $observacion) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAnterior = $fondo->SaldoCajaChica;
            $saldoNuevo = $saldoAnterior + $monto;

            $fondo->SaldoCajaChica = $saldoNuevo;
            $fondo->save();

            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'INGRESO_CAJA_CHICA',
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
     * Registrar egreso de Caja Chica (por gasto).
     */
    public function registrarEgresoCajaChica($sedeId, $monto, $gastoId, $usuarioId)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $gastoId, $usuarioId) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            if ($fondo->SaldoCajaChica < $monto) {
                $saldoActual = number_format($fondo->SaldoCajaChica, 2);
                throw ValidationException::withMessages([
                    'Monto' => "Saldo insuficiente en Caja Chica. Disponible: S/ {$saldoActual}"
                ]);
            }

            $saldoAnterior = $fondo->SaldoCajaChica;
            $saldoNuevo = $saldoAnterior - $monto;

            $fondo->SaldoCajaChica = $saldoNuevo;
            $fondo->save();

            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'EGRESO_CAJA_CHICA',
                'Monto' => -$monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Gasto #{$gastoId} desde Caja Chica",
            ]);

            return $fondo;
        });
    }

    /**
     * Transferir dinero entre Caja Abierta y Caja Chica de la misma sede.
     */
    public function transferirEntreCajas($sedeId, $deCajaAbierta, $monto, $usuarioId, $observacion = '')
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $deCajaAbierta, $monto, $usuarioId, $observacion) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            if ($deCajaAbierta) {
                // De Caja Abierta → Caja Chica
                if ($fondo->Saldo < $monto) {
                    throw ValidationException::withMessages([
                        'Monto' => 'Saldo insuficiente en Caja Abierta. Disponible: S/ ' . number_format($fondo->Saldo, 2)
                    ]);
                }

                $saldoAntCA = $fondo->Saldo;
                $saldoAntCC = $fondo->SaldoCajaChica;

                $fondo->Saldo -= $monto;
                $fondo->SaldoCajaChica += $monto;
                $fondo->save();

                MovimientoFondo::create([
                    'SedeID' => $sedeId,
                    'Tipo' => 'TRASLADO_CA_A_CC',
                    'Monto' => -$monto,
                    'SaldoAnterior' => $saldoAntCA,
                    'SaldoNuevo' => $fondo->Saldo,
                    'UsuarioID' => $usuarioId,
                    'Observacion' => $observacion ?: 'Traslado de Caja Abierta a Caja Chica',
                ]);
            } else {
                // De Caja Chica → Caja Abierta
                if ($fondo->SaldoCajaChica < $monto) {
                    throw ValidationException::withMessages([
                        'Monto' => 'Saldo insuficiente en Caja Chica. Disponible: S/ ' . number_format($fondo->SaldoCajaChica, 2)
                    ]);
                }

                $saldoAntCC = $fondo->SaldoCajaChica;
                $saldoAntCA = $fondo->Saldo;

                $fondo->SaldoCajaChica -= $monto;
                $fondo->Saldo += $monto;
                $fondo->save();

                MovimientoFondo::create([
                    'SedeID' => $sedeId,
                    'Tipo' => 'TRASLADO_CC_A_CA',
                    'Monto' => $monto,
                    'SaldoAnterior' => $saldoAntCC,
                    'SaldoNuevo' => $fondo->SaldoCajaChica,
                    'UsuarioID' => $usuarioId,
                    'Observacion' => $observacion ?: 'Traslado de Caja Chica a Caja Abierta',
                ]);
            }

            return $fondo;
        });
    }

    /**
     * Crear y enviar una transferencia a otra sede.
     * Soporta cuenta origen/destino: CAJA_ABIERTA o CAJA_CHICA
     */
    public function crearTransferencia($sedeOrigenId, $sedeDestinoId, $monto, $usuarioId, $observacion, $cuentaOrigen = 'CAJA_ABIERTA', $cuentaDestino = 'CAJA_ABIERTA')
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }
        if ($sedeOrigenId == $sedeDestinoId) {
            throw ValidationException::withMessages(['SedeDestinoID' => 'No puedes transferir a la misma sede.']);
        }

        $fondoOrigen = FondoSede::where('SedeID', $sedeOrigenId)->first();

        // Validar saldo según cuenta origen
        if ($cuentaOrigen === 'CAJA_CHICA') {
            if (!$fondoOrigen || $fondoOrigen->SaldoCajaChica < $monto) {
                throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en Caja Chica para esta transferencia.']);
            }
        } else {
            if (!$fondoOrigen || $fondoOrigen->Saldo < $monto) {
                throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en Caja Abierta para esta transferencia.']);
            }
        }

        return TransferenciaSede::create([
            'SedeOrigenID' => $sedeOrigenId,
            'SedeDestinoID' => $sedeDestinoId,
            'CuentaOrigen' => $cuentaOrigen,
            'CuentaDestino' => $cuentaDestino,
            'UsuarioOrigenID' => $usuarioId,
            'Monto' => $monto,
            'Estado' => 'PENDIENTE',
            'Observacion' => $observacion,
            'FechaTransferencia' => now(),
        ]);
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
            $cuentaOrigen = $transferencia->CuentaOrigen ?? 'CAJA_ABIERTA';
            $cuentaDestino = $transferencia->CuentaDestino ?? 'CAJA_ABIERTA';

            // 1. Bloquear y verificar saldo en Sede Origen
            $fondoOrigen = FondoSede::lockForUpdate()->where('SedeID', $transferencia->SedeOrigenID)->first();

            if ($cuentaOrigen === 'CAJA_CHICA') {
                if (!$fondoOrigen || $fondoOrigen->SaldoCajaChica < $transferencia->Monto) {
                    throw ValidationException::withMessages(['saldo' => 'La sede origen ya no cuenta con saldo suficiente en Caja Chica.']);
                }
                // 2. Descontar de Caja Chica Origen
                $saldoAnteriorOrigen = $fondoOrigen->SaldoCajaChica;
                $saldoNuevoOrigen = $saldoAnteriorOrigen - $transferencia->Monto;
                $fondoOrigen->SaldoCajaChica = $saldoNuevoOrigen;
            } else {
                if (!$fondoOrigen || $fondoOrigen->Saldo < $transferencia->Monto) {
                    throw ValidationException::withMessages(['saldo' => 'La sede origen ya no cuenta con saldo suficiente en Caja Abierta.']);
                }
                // 2. Descontar de Caja Abierta Origen
                $saldoAnteriorOrigen = $fondoOrigen->Saldo;
                $saldoNuevoOrigen = $saldoAnteriorOrigen - $transferencia->Monto;
                $fondoOrigen->Saldo = $saldoNuevoOrigen;
            }
            $fondoOrigen->save();

            // 3. Registrar Movimiento Salida (Auditoría Origen)
            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeOrigenID,
                'Tipo' => 'ENVIO_TRANSFERENCIA',
                'Monto' => -$transferencia->Monto,
                'SaldoAnterior' => $saldoAnteriorOrigen,
                'SaldoNuevo' => $saldoNuevoOrigen,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $transferencia->UsuarioOrigenID,
                'Observacion' => "Transferencia ID: {$transferencia->TransferenciaID} aceptada. Salida de {$cuentaOrigen}.",
            ]);

            // 4. Sumar a Sede Destino
            $fondoDestino = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeDestinoID],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            if ($cuentaDestino === 'CAJA_CHICA') {
                $saldoAnteriorDestino = $fondoDestino->SaldoCajaChica;
                $saldoNuevoDestino = $saldoAnteriorDestino + $transferencia->Monto;
                $fondoDestino->SaldoCajaChica = $saldoNuevoDestino;
            } else {
                $saldoAnteriorDestino = $fondoDestino->Saldo;
                $saldoNuevoDestino = $saldoAnteriorDestino + $transferencia->Monto;
                $fondoDestino->Saldo = $saldoNuevoDestino;
            }
            $fondoDestino->save();

            // 5. Registrar Movimiento Ingreso (Auditoría Destino)
            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeDestinoID,
                'Tipo' => 'RECEPCION_TRANSFERENCIA',
                'Monto' => $transferencia->Monto,
                'SaldoAnterior' => $saldoAnteriorDestino,
                'SaldoNuevo' => $saldoNuevoDestino,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Transferencia aceptada desde sede {$transferencia->SedeOrigenID}. Ingreso a {$cuentaDestino}.",
            ]);

            // 6. Actualizar transferencia
            $transferencia->update([
                'Estado' => 'ACEPTADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
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
            // Ya no es necesario devolver fondos porque no se descuentan al crear.
            $transferencia->update([
                'Estado' => 'RECHAZADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
            ]);

            return $transferencia;
        });
    }

    /**
     * Verificar si una sede tiene saldo suficiente.
     */
    public function verificarSaldo($sedeId, $monto)
    {
        $fondo = FondoSede::where('SedeID', $sedeId)->first();
        if (!$fondo || $fondo->Saldo < $monto) {
            $saldoActual = $fondo ? number_format($fondo->Saldo, 2) : '0.00';
            throw ValidationException::withMessages(['Monto' => "Saldo insuficiente en Caja Abierta. Saldo disponible: S/ {$saldoActual}. Monto requerido: S/ " . number_format($monto, 2)]);
        }
        return $fondo;
    }

    /**
     * Registrar egreso por colocación de crédito (desembolso de préstamo).
     */
    public function registrarEgresoColocacion($sedeId, $monto, $creditoId, $usuarioId)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $creditoId, $usuarioId) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAnterior = $fondo->Saldo;
            $saldoNuevo = $saldoAnterior - $monto;

            $fondo->Saldo = $saldoNuevo;
            $fondo->save();

            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'EGRESO_COLOCACION',
                'Monto' => -$monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Desembolso de crédito #{$creditoId}",
            ]);

            return $fondo;
        });
    }

    /**
     * Registrar ingreso por recaudo (pago de cliente).
     */
    public function registrarIngresoRecaudo($sedeId, $monto, $pagoId, $usuarioId)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $pagoId, $usuarioId) {
            $fondo = FondoSede::lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAnterior = $fondo->Saldo;
            $saldoNuevo = $saldoAnterior + $monto;

            $fondo->Saldo = $saldoNuevo;
            $fondo->save();

            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'INGRESO_RECAUDO',
                'Monto' => $monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Recaudo de pago #{$pagoId}",
            ]);

            return $fondo;
        });
    }
}
