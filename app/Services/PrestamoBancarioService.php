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

        if ($monto <= 0 || $cuotas < 1 || $tea < 0 || !$fechaDesembolso || $diaPago < 1 || $diaPago > 31) {
            return [];
        }

        $tasaMensual = pow(1 + ($tea / 100), 1 / 12) - 1;
        $cuotaBase = $tasaMensual == 0
            ? round($monto / $cuotas, 2)
            : round($monto * $tasaMensual / (1 - pow(1 + $tasaMensual, -$cuotas)), 2);
        $saldo = $monto;
        $cronograma = [];

        for ($numero = 1; $numero <= $cuotas; $numero++) {
            $fecha = $this->fechaCuota($fechaDesembolso, $diaPago, $numero);
            $interes = round($saldo * $tasaMensual, 2);
            $capital = $numero === $cuotas ? $saldo : round($cuotaBase - $interes, 2);
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

        return DB::transaction(function () use ($data, $cronograma) {
            $ultimaCuota = collect($cronograma)->sortBy('Numero')->last();
            $prestamo = PrestamoBancario::create([
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
            $cuota = CuotaPrestamoBancario::lockForUpdate()->with('prestamo.cuentaTesoreria')->findOrFail($cuota->CuotaPrestamoBancarioID);
            if ($cuota->Estado !== CuotaPrestamoBancario::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages(['cuota' => 'La cuota ya se encuentra cancelada.']);
            }

            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $monto = round((float) $cuota->MontoCuota, 2);
            $fondo = $this->obtenerFondoGerenciaBloqueado();
            if (!$fondo || (float) $fondo->Saldo < $monto) {
                throw ValidationException::withMessages(['cuota' => 'Saldo insuficiente en la Caja Abierta de Gerencia.']);
            }

            $ahora = now();
            $saldoAnterior = round((float) $fondo->Saldo, 2);
            $saldoNuevo = round($saldoAnterior - $monto, 2);
            $fondo->Saldo = $saldoNuevo;
            $fondo->updated_at = $ahora;
            $fondo->save();

            $prestamo = $cuota->prestamo;
            $destino = $this->nombreDestinoPrestamo($prestamo);
            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_PAGO_PRESTAMO_BANCARIO,
                'OrigenTipo' => MovimientoTesoreria::CAJA_GERENCIA,
                'CuentaOrigenNombre' => 'Caja Abierta - Gerencia',
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
            $pago = PagoPrestamoBancario::lockForUpdate()->with(['cuota.prestamo.cuentaTesoreria', 'movimiento'])->findOrFail($pago->PagoPrestamoBancarioID);
            if ($pago->PagoOriginalID || $pago->extorno()->exists()) {
                throw ValidationException::withMessages(['pago' => 'Este pago no puede extornarse o ya fue extornado.']);
            }

            $cuota = CuotaPrestamoBancario::lockForUpdate()->findOrFail($pago->CuotaPrestamoBancarioID);
            $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());
            $fondo = $this->obtenerFondoGerenciaBloqueado();
            if (!$fondo) {
                throw ValidationException::withMessages(['pago' => 'No se encontro la Caja Abierta de Gerencia.']);
            }

            $ahora = now();
            $saldoAnterior = round((float) $fondo->Saldo, 2);
            $saldoNuevo = round($saldoAnterior + (float) $pago->Monto, 2);
            $fondo->Saldo = $saldoNuevo;
            $fondo->updated_at = $ahora;
            $fondo->save();

            $prestamo = $cuota->prestamo;
            $movimiento = MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_EXTORNO_PAGO_PRESTAMO,
                'OrigenTipo' => MovimientoTesoreria::PRESTAMO_BANCARIO,
                'CuentaOrigenNombre' => $this->nombreDestinoPrestamo($prestamo),
                'DestinoTipo' => MovimientoTesoreria::CAJA_GERENCIA,
                'CuentaDestinoNombre' => 'Caja Abierta - Gerencia',
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
                if (!isset($fila[$campo]) || (float) $fila[$campo] < 0) {
                    throw ValidationException::withMessages(['Cronograma' => "La cuota " . ($indice + 1) . " contiene importes invalidos."]);
                }
            }
            if (round((float) $fila['Capital'] + (float) $fila['Interes'] + (float) $fila['Comision'] + (float) $fila['Seguros'], 2) !== round((float) $fila['MontoCuota'], 2)) {
                throw ValidationException::withMessages(['Cronograma' => "La cuota " . ($indice + 1) . " no cuadra con sus componentes."]);
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
        return trim("{$banco} - Préstamo {$prestamo->CuentaPrestamo}" . ($prestamo->Operacion ? " / Operación {$prestamo->Operacion}" : ''));
    }

    private function obtenerFondoGerenciaBloqueado(): ?FondoSede
    {
        return FondoSede::withoutGlobalScope('sede')
            ->whereHas('sede', fn ($query) => $query->where('Nombre', 'like', '%Gerencia%'))
            ->lockForUpdate()
            ->first();
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
