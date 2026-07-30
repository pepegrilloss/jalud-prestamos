<?php

namespace App\Services;

use App\Models\CuentaTesoreria;
use App\Models\CuotaPrestamoBancario;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use App\Models\PagoPrestamoBancario;
use App\Models\PrestamoBancario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrestamoBancarioService
{
    public function generarCronograma(array $data): array
    {
        $monto = round((float) ($data['MontoPrestamo'] ?? 0), 2);
        $cuotas = (int) ($data['NumeroCuotas'] ?? 0);
        $tea = (float) ($data['TEA'] ?? 0);
        $diaPago = (int) ($data['DiaPago'] ?? 0);
        $fechaDesembolso = isset($data['FechaDesembolso']) ? Carbon::parse($data['FechaDesembolso'])->startOfDay() : null;
        $pagoMensualManual = isset($data['PagoMensual']) && (float) $data['PagoMensual'] > 0
            ? round((float) $data['PagoMensual'], 2)
            : null;

        if ($monto <= 0 || $cuotas < 1 || $tea < 0 || ! $fechaDesembolso || $diaPago < 1 || $diaPago > 31) {
            return [];
        }

        $tasaMensual = pow(1 + ($tea / 100), 1 / 12) - 1;
        $cuotaBase = $tasaMensual == 0
            ? round($monto / $cuotas, 2)
            : round($monto * $tasaMensual / (1 - pow(1 + $tasaMensual, -$cuotas)), 2);
        if ($pagoMensualManual !== null) {
            $cuotaBase = $pagoMensualManual;
        }

        $saldo = $monto;
        $cronograma = [];

        for ($numero = 1; $numero <= $cuotas; $numero++) {
            $fecha = $this->fechaCuota($fechaDesembolso, $diaPago, $numero);
            $interes = round($saldo * $tasaMensual, 2);
            $capital = $numero === $cuotas ? $saldo : round($cuotaBase - $interes, 2);
            if ($capital < 0) {
                return [];
            }
            $montoCuota = round($capital + $interes, 2);
            $saldo = round(max(0, $saldo - $capital), 2);

            $cronograma[] = [
                'Numero' => $numero,
                'FechaVencimiento' => $fecha->toDateString(),
                'Capital' => $capital,
                'Interes' => $interes,
                'Comision' => 0,
                'Seguros' => 0,
                'MontoCuota' => $montoCuota,
                'SaldoDeuda' => $saldo,
            ];
        }

        return $cronograma;
    }

    public function calcularTed(float $tea): float
    {
        return round((pow(1 + ($tea / 100), 1 / 360) - 1) * 100, 6);
    }

    public function crearPrestamo(array $data): PrestamoBancario
    {
        $cronograma = $data['Cronograma'] ?? $this->generarCronograma($data);
        $this->validarPrestamo($data, $cronograma);
        $cuentaTesoreriaId = filled($data['CuentaTesoreriaID'] ?? null)
            ? (int) $data['CuentaTesoreriaID']
            : null;
        if ($cuentaTesoreriaId) {
            $cuenta = CuentaTesoreria::query()
                ->whereKey($cuentaTesoreriaId)
                ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)
                ->first();
            if (! $cuenta || $cuenta->Banco !== trim($data['Banco'])) {
                throw ValidationException::withMessages([
                    'CuentaTesoreriaID' => 'La cuenta de pago debe estar activa y pertenecer al banco seleccionado.',
                ]);
            }
        }

        return DB::transaction(function () use ($data, $cronograma, $cuentaTesoreriaId) {
            $ultimaCuota = collect($cronograma)->sortBy('Numero')->last();
            $prestamo = PrestamoBancario::create([
                'CuentaTesoreriaID' => $cuentaTesoreriaId,
                'Banco' => trim($data['Banco']),
                'Cliente' => trim($data['Cliente']),
                'CuentaPrestamo' => trim($data['CuentaPrestamo']),
                'Operacion' => filled($data['Operacion'] ?? null) ? trim($data['Operacion']) : null,
                'MontoPrestamo' => round((float) $data['MontoPrestamo'], 2),
                'FechaDesembolso' => Carbon::parse($data['FechaDesembolso'])->toDateString(),
                'FechaVencimiento' => Carbon::parse($data['FechaVencimiento'] ?? $ultimaCuota['FechaVencimiento'])->toDateString(),
                'NumeroCuotas' => count($cronograma),
                'DiaPago' => (int) $data['DiaPago'],
                'PagoMensual' => round((float) ($data['PagoMensual'] ?? $cronograma[0]['MontoCuota']), 2),
                'TEA' => round((float) $data['TEA'], 6),
                'TED' => round((float) ($data['TED'] ?? $this->calcularTed((float) $data['TEA'])), 6),
                'Estado' => PrestamoBancario::ESTADO_VIGENTE,
                'Observaciones' => $data['Observaciones'] ?? null,
            ]);

            foreach ($cronograma as $fila) {
                $prestamo->cuotas()->create([
                    'Numero' => (int) $fila['Numero'],
                    'FechaVencimiento' => $fila['FechaVencimiento'],
                    'Capital' => round((float) $fila['Capital'], 2),
                    'Interes' => round((float) $fila['Interes'], 2),
                    'Comision' => round((float) ($fila['Comision'] ?? 0), 2),
                    'Seguros' => round((float) ($fila['Seguros'] ?? 0), 2),
                    'MontoCuota' => round((float) $fila['MontoCuota'], 2),
                    'SaldoDeuda' => round((float) $fila['SaldoDeuda'], 2),
                    'Estado' => CuotaPrestamoBancario::ESTADO_PENDIENTE,
                ]);
            }

            return $prestamo;
        });
    }

    public function pagarCuota(CuotaPrestamoBancario $cuota, array $data, int $usuarioId): PagoPrestamoBancario
    {
        return DB::transaction(function () use ($cuota, $data, $usuarioId) {
            $prestamo = PrestamoBancario::lockForUpdate()
                ->with('cuentaTesoreria')
                ->findOrFail($cuota->PrestamoBancarioID);
            if ($prestamo->Estado !== PrestamoBancario::ESTADO_VIGENTE) {
                throw ValidationException::withMessages(['cuota' => 'El prestamo ya no se encuentra vigente.']);
            }

            $cuota = CuotaPrestamoBancario::lockForUpdate()->findOrFail($cuota->CuotaPrestamoBancarioID);
            if ($cuota->Estado !== CuotaPrestamoBancario::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages(['cuota' => 'La cuota ya se encuentra cancelada.']);
            }

            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $monto = round((float) $cuota->MontoCuota, 2);
            $ahora = now();
            $origen = $this->obtenerOrigenPagoBloqueado($prestamo);
            [$saldoAnterior, $saldoNuevo] = $this->debitarOrigen($origen, $monto, $ahora);
            $destino = $this->nombreDestinoPrestamo($prestamo);
            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_PAGO_PRESTAMO_BANCARIO,
                'OrigenTipo' => $origen['tipo'],
                'CuentaOrigenID' => $origen['cuenta_id'],
                'CuentaOrigenNombre' => $origen['nombre'],
                'DestinoTipo' => MovimientoTesoreria::PRESTAMO_BANCARIO,
                'CuentaDestinoNombre' => $destino,
                'Monto' => $monto,
                'FechaContable' => $fechaContable,
                'FechaMovimiento' => $ahora,
                'Concepto' => trim($data['Concepto'] ?? "Pago cuota {$cuota->Numero} - {$prestamo->Cliente}"),
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => $cuota->CuotaPrestamoBancarioID,
                'SaldoAnteriorOrigen' => $saldoAnterior,
                'SaldoNuevoOrigen' => $saldoNuevo,
            ]);

            $pago = PagoPrestamoBancario::create([
                'Tipo' => PagoPrestamoBancario::TIPO_PAGO_CUOTA,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => $cuota->CuotaPrestamoBancarioID,
                'MovimientoTesoreriaID' => $movimiento->MovimientoTesoreriaID,
                'Monto' => $monto,
                'FechaContable' => $fechaContable,
                'FechaRegistro' => $ahora,
                'Concepto' => $movimiento->Concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
            ]);

            $cuota->update(['Estado' => CuotaPrestamoBancario::ESTADO_CANCELADA, 'FechaPago' => $fechaContable]);
            $this->actualizarEstadoPrestamo($prestamo);

            return $pago;
        });
    }

    public function extornarPago(PagoPrestamoBancario $pago, array $data, int $usuarioId): PagoPrestamoBancario
    {
        return DB::transaction(function () use ($pago, $data, $usuarioId) {
            $pago = PagoPrestamoBancario::lockForUpdate()
                ->with('movimiento')
                ->findOrFail($pago->PagoPrestamoBancarioID);
            if ($pago->Tipo !== PagoPrestamoBancario::TIPO_PAGO_CUOTA || $pago->PagoOriginalID || $pago->extorno()->exists()) {
                throw ValidationException::withMessages(['pago' => 'Este pago no puede extornarse o ya fue extornado.']);
            }

            $prestamo = PrestamoBancario::lockForUpdate()
                ->with('cuentaTesoreria')
                ->findOrFail($pago->PrestamoBancarioID);
            $cuota = CuotaPrestamoBancario::lockForUpdate()->findOrFail($pago->CuotaPrestamoBancarioID);
            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $ahora = now();
            $origenOriginal = $this->obtenerOrigenMovimientoBloqueado($pago->movimiento);
            [$saldoAnterior, $saldoNuevo] = $this->acreditarOrigen($origenOriginal, (float) $pago->Monto, $ahora);
            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_EXTORNO_PAGO_PRESTAMO,
                'OrigenTipo' => MovimientoTesoreria::PRESTAMO_BANCARIO,
                'CuentaOrigenNombre' => $this->nombreDestinoPrestamo($prestamo),
                'DestinoTipo' => $origenOriginal['tipo'],
                'CuentaDestinoID' => $origenOriginal['cuenta_id'],
                'CuentaDestinoNombre' => $origenOriginal['nombre'],
                'Monto' => $pago->Monto,
                'FechaContable' => $fechaContable,
                'FechaMovimiento' => $ahora,
                'Concepto' => trim($data['Concepto'] ?? "Extorno de pago de cuota {$cuota->Numero}"),
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'MovimientoOriginalID' => $pago->MovimientoTesoreriaID,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => $cuota->CuotaPrestamoBancarioID,
                'SaldoAnteriorDestino' => $saldoAnterior,
                'SaldoNuevoDestino' => $saldoNuevo,
            ]);

            $extorno = PagoPrestamoBancario::create([
                'Tipo' => PagoPrestamoBancario::TIPO_EXTORNO_CUOTA,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => $cuota->CuotaPrestamoBancarioID,
                'MovimientoTesoreriaID' => $movimiento->MovimientoTesoreriaID,
                'Monto' => $pago->Monto,
                'FechaContable' => $fechaContable,
                'FechaRegistro' => $ahora,
                'Concepto' => $movimiento->Concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'PagoOriginalID' => $pago->PagoPrestamoBancarioID,
            ]);

            $cuota->update(['Estado' => CuotaPrestamoBancario::ESTADO_PENDIENTE, 'FechaPago' => null]);
            $prestamo->update(['Estado' => PrestamoBancario::ESTADO_VIGENTE]);

            return $extorno;
        });
    }

    public function configurarCuentaPago(PrestamoBancario $prestamo, ?int $cuentaTesoreriaId): PrestamoBancario
    {
        return DB::transaction(function () use ($prestamo, $cuentaTesoreriaId) {
            $prestamo = PrestamoBancario::lockForUpdate()->findOrFail($prestamo->PrestamoBancarioID);
            if ($prestamo->Estado !== PrestamoBancario::ESTADO_VIGENTE) {
                throw ValidationException::withMessages([
                    'CuentaTesoreriaID' => 'Solo se puede cambiar el origen de pago de un prestamo vigente.',
                ]);
            }

            if ($cuentaTesoreriaId) {
                $cuenta = CuentaTesoreria::lockForUpdate()
                    ->whereKey($cuentaTesoreriaId)
                    ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)
                    ->first();
                if (! $cuenta || $cuenta->Banco !== $prestamo->NombreBanco) {
                    throw ValidationException::withMessages([
                        'CuentaTesoreriaID' => 'La cuenta debe estar activa y pertenecer al banco del prestamo.',
                    ]);
                }
            }

            $prestamo->CuentaTesoreriaID = $cuentaTesoreriaId;
            $prestamo->save();

            return $prestamo->refresh();
        });
    }

    public function cancelarAnticipadamente(
        PrestamoBancario $prestamo,
        array $data,
        int $usuarioId
    ): PagoPrestamoBancario {
        return DB::transaction(function () use ($prestamo, $data, $usuarioId) {
            $prestamo = PrestamoBancario::lockForUpdate()
                ->with('cuentaTesoreria')
                ->findOrFail($prestamo->PrestamoBancarioID);
            if ($prestamo->Estado !== PrestamoBancario::ESTADO_VIGENTE) {
                throw ValidationException::withMessages([
                    'prestamo' => 'Solo se puede cancelar anticipadamente un prestamo vigente.',
                ]);
            }

            $cuotasPendientes = $prestamo->cuotas()
                ->where('Estado', CuotaPrestamoBancario::ESTADO_PENDIENTE)
                ->lockForUpdate()
                ->get();
            $capitalPendiente = round((float) $cuotasPendientes->sum('Capital'), 2);
            if ($capitalPendiente <= 0) {
                throw ValidationException::withMessages([
                    'prestamo' => 'El prestamo no tiene capital pendiente por amortizar.',
                ]);
            }

            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $ahora = now();
            $origen = $this->obtenerOrigenPagoBloqueado($prestamo);
            [$saldoAnterior, $saldoNuevo] = $this->debitarOrigen($origen, $capitalPendiente, $ahora);
            $concepto = trim($data['Concepto'] ?? "Cancelacion anticipada de capital - {$prestamo->Cliente}");

            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_CANCELACION_ANTICIPADA,
                'OrigenTipo' => $origen['tipo'],
                'CuentaOrigenID' => $origen['cuenta_id'],
                'CuentaOrigenNombre' => $origen['nombre'],
                'DestinoTipo' => MovimientoTesoreria::PRESTAMO_BANCARIO,
                'CuentaDestinoNombre' => $this->nombreDestinoPrestamo($prestamo),
                'Monto' => $capitalPendiente,
                'FechaContable' => $fechaContable,
                'FechaMovimiento' => $ahora,
                'Concepto' => $concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'SaldoAnteriorOrigen' => $saldoAnterior,
                'SaldoNuevoOrigen' => $saldoNuevo,
            ]);

            $cancelacion = PagoPrestamoBancario::create([
                'Tipo' => PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => null,
                'MovimientoTesoreriaID' => $movimiento->MovimientoTesoreriaID,
                'Monto' => $capitalPendiente,
                'FechaContable' => $fechaContable,
                'FechaRegistro' => $ahora,
                'Concepto' => $concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
            ]);

            foreach ($cuotasPendientes as $cuota) {
                $cuota->Estado = CuotaPrestamoBancario::ESTADO_ANULADA_ANTICIPADA;
                $cuota->FechaPago = null;
                $cuota->save();
            }
            $prestamo->Estado = PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO;
            $prestamo->save();

            return $cancelacion;
        });
    }

    public function extornarCancelacionAnticipada(
        PagoPrestamoBancario $pago,
        array $data,
        int $usuarioId
    ): PagoPrestamoBancario {
        return DB::transaction(function () use ($pago, $data, $usuarioId) {
            $pago = PagoPrestamoBancario::lockForUpdate()
                ->with(['prestamo', 'movimiento'])
                ->findOrFail($pago->PagoPrestamoBancarioID);
            if (
                $pago->Tipo !== PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA
                || $pago->PagoOriginalID
                || $pago->extorno()->exists()
            ) {
                throw ValidationException::withMessages([
                    'pago' => 'Esta cancelacion no puede extornarse o ya fue extornada.',
                ]);
            }

            $prestamo = PrestamoBancario::lockForUpdate()->findOrFail($pago->PrestamoBancarioID);
            if ($prestamo->Estado !== PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO) {
                throw ValidationException::withMessages([
                    'prestamo' => 'El prestamo no se encuentra cancelado anticipadamente.',
                ]);
            }

            $cuotasAnuladas = $prestamo->cuotas()
                ->where('Estado', CuotaPrestamoBancario::ESTADO_ANULADA_ANTICIPADA)
                ->lockForUpdate()
                ->get();
            if ($cuotasAnuladas->isEmpty()) {
                throw ValidationException::withMessages([
                    'prestamo' => 'No se encontraron cuotas anuladas para reabrir.',
                ]);
            }

            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $ahora = now();
            $origenOriginal = $this->obtenerOrigenMovimientoBloqueado($pago->movimiento);
            [$saldoAnterior, $saldoNuevo] = $this->acreditarOrigen($origenOriginal, (float) $pago->Monto, $ahora);
            $concepto = trim($data['Concepto'] ?? "Extorno de cancelacion anticipada #{$pago->PagoPrestamoBancarioID}");

            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_EXTORNO_CANCELACION_ANTICIPADA,
                'OrigenTipo' => MovimientoTesoreria::PRESTAMO_BANCARIO,
                'CuentaOrigenNombre' => $this->nombreDestinoPrestamo($prestamo),
                'DestinoTipo' => $origenOriginal['tipo'],
                'CuentaDestinoID' => $origenOriginal['cuenta_id'],
                'CuentaDestinoNombre' => $origenOriginal['nombre'],
                'Monto' => $pago->Monto,
                'FechaContable' => $fechaContable,
                'FechaMovimiento' => $ahora,
                'Concepto' => $concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'MovimientoOriginalID' => $pago->MovimientoTesoreriaID,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'SaldoAnteriorDestino' => $saldoAnterior,
                'SaldoNuevoDestino' => $saldoNuevo,
            ]);

            $extorno = PagoPrestamoBancario::create([
                'Tipo' => PagoPrestamoBancario::TIPO_EXTORNO_CANCELACION,
                'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
                'CuotaPrestamoBancarioID' => null,
                'MovimientoTesoreriaID' => $movimiento->MovimientoTesoreriaID,
                'Monto' => $pago->Monto,
                'FechaContable' => $fechaContable,
                'FechaRegistro' => $ahora,
                'Concepto' => $concepto,
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'PagoOriginalID' => $pago->PagoPrestamoBancarioID,
            ]);

            foreach ($cuotasAnuladas as $cuota) {
                $cuota->Estado = CuotaPrestamoBancario::ESTADO_PENDIENTE;
                $cuota->save();
            }
            $prestamo->Estado = PrestamoBancario::ESTADO_VIGENTE;
            $prestamo->save();

            return $extorno;
        });
    }

    private function validarPrestamo(array $data, array $cronograma): void
    {
        if (empty($data['Banco']) || empty($data['Cliente']) || empty($data['CuentaPrestamo'])) {
            throw ValidationException::withMessages(['Banco' => 'Banco, cliente y cuenta del prestamo son obligatorios.']);
        }
        if ((float) ($data['MontoPrestamo'] ?? 0) <= 0 || (float) ($data['TEA'] ?? -1) < 0) {
            throw ValidationException::withMessages(['MontoPrestamo' => 'Monto y TEA deben ser validos.']);
        }
        if (count($cronograma) !== (int) ($data['NumeroCuotas'] ?? 0)) {
            throw ValidationException::withMessages(['Cronograma' => 'El cronograma debe contener todas las cuotas.']);
        }

        $capital = 0;
        foreach ($cronograma as $indice => $fila) {
            foreach (['Capital', 'Interes', 'Comision', 'Seguros', 'MontoCuota', 'SaldoDeuda'] as $campo) {
                if (! isset($fila[$campo]) || (float) $fila[$campo] < 0) {
                    throw ValidationException::withMessages(['Cronograma' => 'La cuota '.($indice + 1).' contiene importes invalidos.']);
                }
            }
            if (round((float) $fila['Capital'] + (float) $fila['Interes'] + (float) $fila['Comision'] + (float) $fila['Seguros'], 2) !== round((float) $fila['MontoCuota'], 2)) {
                throw ValidationException::withMessages(['Cronograma' => 'La cuota '.($indice + 1).' no cuadra con sus componentes.']);
            }
            $capital += (float) $fila['Capital'];
        }

        if (abs(round($capital, 2) - round((float) $data['MontoPrestamo'], 2)) > 0.02) {
            throw ValidationException::withMessages(['Cronograma' => 'La suma de capital del cronograma debe coincidir con el prestamo.']);
        }
    }

    private function fechaCuota(Carbon $fechaDesembolso, int $diaPago, int $numero): Carbon
    {
        $fecha = $fechaDesembolso->copy()->startOfMonth()->addMonths($numero);
        $fecha->day(min($diaPago, $fecha->daysInMonth));

        return $fecha;
    }

    private function actualizarEstadoPrestamo(PrestamoBancario $prestamo): void
    {
        $pendientes = $prestamo->cuotas()->where('Estado', CuotaPrestamoBancario::ESTADO_PENDIENTE)->exists();
        $prestamo->update(['Estado' => $pendientes ? PrestamoBancario::ESTADO_VIGENTE : PrestamoBancario::ESTADO_CANCELADO]);
    }

    private function nombreDestinoPrestamo(PrestamoBancario $prestamo): string
    {
        $banco = $prestamo->NombreBanco;

        return trim("{$banco} - Préstamo {$prestamo->CuentaPrestamo}".($prestamo->Operacion ? " / Operación {$prestamo->Operacion}" : ''));
    }

    private function obtenerFondoGerenciaBloqueado(): ?FondoSede
    {
        return FondoSede::withoutGlobalScope('sede')
            ->whereHas('sede', fn ($query) => $query->where('Nombre', 'like', '%Gerencia%'))
            ->lockForUpdate()
            ->first();
    }

    private function obtenerOrigenPagoBloqueado(PrestamoBancario $prestamo): array
    {
        if ($prestamo->CuentaTesoreriaID) {
            $cuenta = CuentaTesoreria::lockForUpdate()->find($prestamo->CuentaTesoreriaID);
            if (! $cuenta || $cuenta->Estado !== CuentaTesoreria::ESTADO_ACTIVA) {
                throw ValidationException::withMessages([
                    'cuota' => 'La cuenta bancaria asociada al prestamo no se encuentra activa.',
                ]);
            }

            return [
                'modelo' => $cuenta,
                'es_caja' => false,
                'tipo' => MovimientoTesoreria::CUENTA_BANCARIA,
                'cuenta_id' => $cuenta->CuentaTesoreriaID,
                'nombre' => $cuenta->NombreCompleto,
                'saldo' => round((float) $cuenta->SaldoActual, 2),
            ];
        }

        $fondo = $this->obtenerFondoGerenciaBloqueado();
        if (! $fondo) {
            throw ValidationException::withMessages([
                'cuota' => 'No se encontro la Caja Abierta de Gerencia.',
            ]);
        }

        return [
            'modelo' => $fondo,
            'es_caja' => true,
            'tipo' => MovimientoTesoreria::CAJA_GERENCIA,
            'cuenta_id' => null,
            'nombre' => 'Caja Abierta - Gerencia',
            'saldo' => round((float) $fondo->Saldo, 2),
        ];
    }

    private function obtenerOrigenMovimientoBloqueado(MovimientoTesoreria $movimiento): array
    {
        if ($movimiento->OrigenTipo === MovimientoTesoreria::CUENTA_BANCARIA && $movimiento->CuentaOrigenID) {
            $cuenta = CuentaTesoreria::lockForUpdate()->find($movimiento->CuentaOrigenID);
            if (! $cuenta) {
                throw ValidationException::withMessages([
                    'pago' => 'No se encontro la cuenta bancaria original del pago.',
                ]);
            }

            return [
                'modelo' => $cuenta,
                'es_caja' => false,
                'tipo' => MovimientoTesoreria::CUENTA_BANCARIA,
                'cuenta_id' => $cuenta->CuentaTesoreriaID,
                'nombre' => $cuenta->NombreCompleto,
                'saldo' => round((float) $cuenta->SaldoActual, 2),
            ];
        }

        if ($movimiento->OrigenTipo !== MovimientoTesoreria::CAJA_GERENCIA) {
            throw ValidationException::withMessages([
                'pago' => 'El movimiento original no tiene una cuenta de origen valida.',
            ]);
        }

        $fondo = $this->obtenerFondoGerenciaBloqueado();
        if (! $fondo) {
            throw ValidationException::withMessages([
                'pago' => 'No se encontro la Caja Abierta de Gerencia.',
            ]);
        }

        return [
            'modelo' => $fondo,
            'es_caja' => true,
            'tipo' => MovimientoTesoreria::CAJA_GERENCIA,
            'cuenta_id' => null,
            'nombre' => 'Caja Abierta - Gerencia',
            'saldo' => round((float) $fondo->Saldo, 2),
        ];
    }

    private function debitarOrigen(array $origen, float $monto, Carbon $fecha): array
    {
        $saldoAnterior = round((float) $origen['saldo'], 2);
        if ($saldoAnterior < $monto) {
            throw ValidationException::withMessages([
                'cuota' => 'Saldo insuficiente en '.$origen['nombre']
                    .'. Disponible: S/ '.number_format($saldoAnterior, 2),
            ]);
        }

        $saldoNuevo = round($saldoAnterior - $monto, 2);
        $this->guardarSaldoOrigen($origen, $saldoNuevo, $fecha);

        return [$saldoAnterior, $saldoNuevo];
    }

    private function acreditarOrigen(array $origen, float $monto, Carbon $fecha): array
    {
        $saldoAnterior = round((float) $origen['saldo'], 2);
        $saldoNuevo = round($saldoAnterior + $monto, 2);
        $this->guardarSaldoOrigen($origen, $saldoNuevo, $fecha);

        return [$saldoAnterior, $saldoNuevo];
    }

    private function guardarSaldoOrigen(array $origen, float $saldo, Carbon $fecha): void
    {
        if ($origen['es_caja']) {
            $origen['modelo']->Saldo = $saldo;
            $origen['modelo']->updated_at = $fecha;
            $origen['modelo']->save();

            return;
        }

        $origen['modelo']->SaldoActual = $saldo;
        $origen['modelo']->FechaUltimoMovimiento = $fecha;
        $origen['modelo']->save();
    }

    private function resolverFechaContable(string $fecha): Carbon
    {
        $fechaContable = Carbon::parse($fecha)->startOfDay();
        if ($fechaContable->gt(now()->startOfDay())) {
            throw ValidationException::withMessages(['FechaContable' => 'No se permiten fechas futuras.']);
        }

        return $fechaContable;
    }
}
