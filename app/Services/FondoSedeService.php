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
    public function inyectarCapital($sedeId, $monto, $usuarioId, $observacion = 'Ingreso de Capital Manual', $voucherImagen = null)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }

        return DB::transaction(function () use ($sedeId, $monto, $usuarioId, $observacion, $voucherImagen) {
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAnterior = $fondo->Saldo;
            $saldoNuevo = $saldoAnterior + $monto;

            $fondo->Saldo = $saldoNuevo;
            $fondo->save();

            $movimientoData = [
                'SedeID' => $sedeId,
                'Tipo' => 'INGRESO_CAPITAL',
                'Monto' => $monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => $observacion,
            ];

            if ($voucherImagen) {
                $movimientoData['VoucherImagen'] = $voucherImagen;
            }

            MovimientoFondo::create($movimientoData);

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
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
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
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
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
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
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
    public function crearTransferencia($sedeOrigenId, $sedeDestinoId, $monto, $usuarioId, $observacion, $cuentaOrigen = 'CAJA_ABIERTA', $cuentaDestino = 'CAJA_ABIERTA', $esSolicitudCapital = false, $esSolicitudGerencia = false, $voucherImagen = null)
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a 0.']);
        }
        if ($sedeOrigenId == $sedeDestinoId && $cuentaOrigen === $cuentaDestino) {
            throw ValidationException::withMessages(['SedeDestinoID' => 'No puedes transferir entre la misma cuenta de la misma sede.']);
        }

        if (!$esSolicitudGerencia) {
            $fondoOrigen = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->where('SedeID', $sedeOrigenId)->first();

            if ($cuentaOrigen === 'CAJA_CHICA') {
                if (!$fondoOrigen || $fondoOrigen->SaldoCajaChica < $monto) {
                    throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en Caja Chica para esta transferencia.']);
                }
            } else {
                if (!$fondoOrigen || $fondoOrigen->Saldo < $monto) {
                    throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en Caja Abierta para esta transferencia.']);
                }
            }
        }

        $data = [
            'SedeOrigenID' => $sedeOrigenId,
            'SedeDestinoID' => $sedeDestinoId,
            'CuentaOrigen' => $cuentaOrigen,
            'CuentaDestino' => $cuentaDestino,
            'EsSolicitudCapital' => $esSolicitudCapital,
            'EsSolicitudGerencia' => $esSolicitudGerencia,
            'UsuarioOrigenID' => $usuarioId,
            'Monto' => $monto,
            'Estado' => 'PENDIENTE',
            'Observacion' => $observacion,
            'FechaTransferencia' => now(),
        ];

        if ($voucherImagen) {
            $data['VoucherImagen'] = $voucherImagen;
        }

        return TransferenciaSede::create($data);
    }

    /**
     * Aceptar una transferencia entrante.
     */
    public function aceptarTransferencia(TransferenciaSede $transferencia, $usuarioId, $montoAprobado = null)
    {
        if ($transferencia->Estado !== 'PENDIENTE') {
            throw ValidationException::withMessages(['estado' => 'La transferencia ya ha sido procesada.']);
        }

        $montoEfectivo = $montoAprobado ?? $transferencia->Monto;

        if ($montoEfectivo <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto aprobado debe ser mayor a 0.']);
        }

        if ($montoEfectivo > $transferencia->Monto) {
            throw ValidationException::withMessages(['Monto' => 'El monto aprobado no puede exceder el monto solicitado.']);
        }

        if ($transferencia->EsSolicitudCapital) {
            return $this->aceptarSolicitudCapital($transferencia, $usuarioId, $montoEfectivo);
        }

        return DB::transaction(function () use ($transferencia, $usuarioId, $montoEfectivo) {
            $cuentaOrigen = $transferencia->CuentaOrigen ?? 'CAJA_ABIERTA';
            $cuentaDestino = $transferencia->CuentaDestino ?? 'CAJA_ABIERTA';

            // 1. Bloquear y verificar saldo en Sede Origen
            $fondoOrigen = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->where('SedeID', $transferencia->SedeOrigenID)->first();

            if ($cuentaOrigen === 'CAJA_CHICA') {
                if (!$fondoOrigen || $fondoOrigen->SaldoCajaChica < $montoEfectivo) {
                    throw ValidationException::withMessages(['saldo' => 'La sede origen ya no cuenta con saldo suficiente en Caja Chica.']);
                }
                $saldoAnteriorOrigen = $fondoOrigen->SaldoCajaChica;
                $saldoNuevoOrigen = $saldoAnteriorOrigen - $montoEfectivo;
                $fondoOrigen->SaldoCajaChica = $saldoNuevoOrigen;
            } else {
                if (!$fondoOrigen || $fondoOrigen->Saldo < $montoEfectivo) {
                    throw ValidationException::withMessages(['saldo' => 'La sede origen ya no cuenta con saldo suficiente en Caja Abierta.']);
                }
                $saldoAnteriorOrigen = $fondoOrigen->Saldo;
                $saldoNuevoOrigen = $saldoAnteriorOrigen - $montoEfectivo;
                $fondoOrigen->Saldo = $saldoNuevoOrigen;
            }
            $fondoOrigen->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeOrigenID,
                'Tipo' => 'ENVIO_TRANSFERENCIA',
                'Monto' => -$montoEfectivo,
                'SaldoAnterior' => $saldoAnteriorOrigen,
                'SaldoNuevo' => $saldoNuevoOrigen,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $transferencia->UsuarioOrigenID,
                'Observacion' => "Transferencia ID: {$transferencia->TransferenciaID} aceptada. Salida de {$cuentaOrigen}.",
            ]);

            $fondoDestino = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeDestinoID],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            if ($cuentaDestino === 'CAJA_CHICA') {
                $saldoAnteriorDestino = $fondoDestino->SaldoCajaChica;
                $saldoNuevoDestino = $saldoAnteriorDestino + $montoEfectivo;
                $fondoDestino->SaldoCajaChica = $saldoNuevoDestino;
            } else {
                $saldoAnteriorDestino = $fondoDestino->Saldo;
                $saldoNuevoDestino = $saldoAnteriorDestino + $montoEfectivo;
                $fondoDestino->Saldo = $saldoNuevoDestino;
            }
            $fondoDestino->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeDestinoID,
                'Tipo' => 'RECEPCION_TRANSFERENCIA',
                'Monto' => $montoEfectivo,
                'SaldoAnterior' => $saldoAnteriorDestino,
                'SaldoNuevo' => $saldoNuevoDestino,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Transferencia aceptada desde sede {$transferencia->SedeOrigenID}. Ingreso a {$cuentaDestino}.",
            ]);

            $transferencia->update([
                'Estado' => 'ACEPTADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
                'MontoAprobado' => $montoEfectivo,
            ]);

            return $transferencia;
        });
    }

    /**
     * Aceptar una solicitud de capital: Gerencia → Sede.
     * El dinero sale de Gerencia (SedeDestino) y va a la sede solicitante (SedeOrigen).
     */
    private function aceptarSolicitudCapital(TransferenciaSede $transferencia, $usuarioId, $montoEfectivo): TransferenciaSede
    {
        return DB::transaction(function () use ($transferencia, $usuarioId, $montoEfectivo) {
            $fondoGerencia = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->where('SedeID', $transferencia->SedeDestinoID)->first();
            if (!$fondoGerencia || $fondoGerencia->Saldo < $montoEfectivo) {
                throw ValidationException::withMessages(['saldo' => 'Gerencia no cuenta con saldo suficiente en Caja Abierta.']);
            }

            $saldoAntGerencia = $fondoGerencia->Saldo;
            $saldoNuevoGerencia = $saldoAntGerencia - $montoEfectivo;
            $fondoGerencia->Saldo = $saldoNuevoGerencia;
            $fondoGerencia->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeDestinoID,
                'Tipo' => 'ENVIO_CAPITAL',
                'Monto' => -$montoEfectivo,
                'SaldoAnterior' => $saldoAntGerencia,
                'SaldoNuevo' => $saldoNuevoGerencia,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Capital enviado a {$transferencia->sedeOrigen->Nombre} por solicitud #{$transferencia->TransferenciaID}",
            ]);

            $fondoSede = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeOrigenID],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAntSede = $fondoSede->Saldo;
            $saldoNuevoSede = $saldoAntSede + $montoEfectivo;
            $fondoSede->Saldo = $saldoNuevoSede;
            $fondoSede->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeOrigenID,
                'Tipo' => 'RECEPCION_CAPITAL',
                'Monto' => $montoEfectivo,
                'SaldoAnterior' => $saldoAntSede,
                'SaldoNuevo' => $saldoNuevoSede,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Capital recibido de Gerencia por solicitud #{$transferencia->TransferenciaID}",
            ]);

            $transferencia->update([
                'Estado' => 'ACEPTADO',
                'UsuarioRespondeID' => $usuarioId,
                'FechaRespuesta' => now(),
                'MontoAprobado' => $montoEfectivo,
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
        $fondo = FondoSede::withoutGlobalScope('sede')->where('SedeID', $sedeId)->first();
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
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            if ($fondo->Saldo < $monto) {
                throw ValidationException::withMessages(['Monto' => 'Saldo insuficiente en Caja Abierta. Saldo disponible: S/ ' . number_format($fondo->Saldo, 2)]);
            }

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
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
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

    /**
     * Revertir un ingreso por recaudo cuando se borra un pago.
     * Descuenta el monto de la caja abierta y registra el movimiento de auditoría.
     */
    public function registrarReversionRecaudo($sedeId, $monto, $pagoId, $usuarioId)
    {
        if ($monto <= 0) {
            return;
        }

        return DB::transaction(function () use ($sedeId, $monto, $pagoId, $usuarioId) {
            $fondo = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $sedeId],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAnterior = $fondo->Saldo;
            $saldoNuevo = $saldoAnterior - $monto;

            $fondo->Saldo = $saldoNuevo;
            $fondo->save();

            MovimientoFondo::create([
                'SedeID' => $sedeId,
                'Tipo' => 'REVERSION_RECAUDO',
                'Monto' => -$monto,
                'SaldoAnterior' => $saldoAnterior,
                'SaldoNuevo' => $saldoNuevo,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Reversión por borrado de pago #{$pagoId}",
            ]);

            return $fondo;
        });
    }

    /**
     * Ejecutar una transferencia solicitada por Gerencia: Sede → Gerencia, directo sin aprobación.
     */
    public function ejecutarTransferenciaSolicitada(TransferenciaSede $transferencia, $usuarioId): TransferenciaSede
    {
        if ($transferencia->Estado !== 'PENDIENTE') {
            throw ValidationException::withMessages(['estado' => 'La solicitud ya fue procesada.']);
        }

        if (!$transferencia->EsSolicitudGerencia) {
            throw ValidationException::withMessages(['tipo' => 'Esta transferencia no es una solicitud de Gerencia.']);
        }

        return DB::transaction(function () use ($transferencia, $usuarioId) {
            $fondoSede = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->where('SedeID', $transferencia->SedeDestinoID)->first();
            if (!$fondoSede || $fondoSede->Saldo < $transferencia->Monto) {
                throw ValidationException::withMessages(['saldo' => 'Saldo insuficiente en Caja Abierta para realizar la transferencia.']);
            }

            $saldoAntSede = $fondoSede->Saldo;
            $saldoNuevoSede = $saldoAntSede - $transferencia->Monto;
            $fondoSede->Saldo = $saldoNuevoSede;
            $fondoSede->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeDestinoID,
                'Tipo' => 'ENVIO_TRANSFERENCIA',
                'Monto' => -$transferencia->Monto,
                'SaldoAnterior' => $saldoAntSede,
                'SaldoNuevo' => $saldoNuevoSede,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Transferencia a Gerencia por solicitud #{$transferencia->TransferenciaID}",
            ]);

            $fondoGerencia = FondoSede::withoutGlobalScope('sede')->lockForUpdate()->firstOrCreate(
                ['SedeID' => $transferencia->SedeOrigenID],
                ['Saldo' => 0, 'SaldoCajaChica' => 0]
            );

            $saldoAntGerencia = $fondoGerencia->Saldo;
            $saldoNuevoGerencia = $saldoAntGerencia + $transferencia->Monto;
            $fondoGerencia->Saldo = $saldoNuevoGerencia;
            $fondoGerencia->save();

            MovimientoFondo::create([
                'SedeID' => $transferencia->SedeOrigenID,
                'Tipo' => 'RECEPCION_TRANSFERENCIA',
                'Monto' => $transferencia->Monto,
                'SaldoAnterior' => $saldoAntGerencia,
                'SaldoNuevo' => $saldoNuevoGerencia,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => $usuarioId,
                'Observacion' => "Recepción desde {$transferencia->sedeDestino->Nombre} por solicitud #{$transferencia->TransferenciaID}",
            ]);

            $transferencia->update([
                'Estado' => 'ACEPTADO',
                'UsuarioRespondeID' => $usuarioId,
                'MontoAprobado' => $transferencia->Monto,
                'FechaRespuesta' => now(),
            ]);

            return $transferencia;
        });
    }
}
