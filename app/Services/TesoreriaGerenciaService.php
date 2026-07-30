<?php

namespace App\Services;

use App\Models\CuentaTesoreria;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TesoreriaGerenciaService
{
    public const CAJA_GERENCIA_KEY = 'CAJA_GERENCIA';

    public function crearCuentaBancaria(array $data, int $usuarioId): CuentaTesoreria
    {
        $montoInicial = round((float) ($data['SaldoInicial'] ?? 0), 2);
        if ($montoInicial < 0) {
            throw ValidationException::withMessages(['SaldoInicial' => 'El saldo inicial no puede ser negativo.']);
        }
        $fechaContable = $this->resolverFechaContable($data['FechaContable'] ?? now()->toDateString());

        return DB::transaction(function () use ($data, $usuarioId, $montoInicial, $fechaContable) {
            $cuenta = CuentaTesoreria::create([
                'Banco' => trim($data['Banco']),
                'NumeroCuenta' => trim($data['NumeroCuenta']),
                'TipoCuenta' => 'BANCO',
                'SaldoActual' => $montoInicial,
                'FechaUltimoMovimiento' => now(),
                'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
            ]);

            MovimientoTesoreria::create([
                'Tipo' => MovimientoTesoreria::TIPO_APERTURA,
                'OrigenTipo' => MovimientoTesoreria::APERTURA,
                'CuentaOrigenNombre' => 'Apertura de cuenta',
                'DestinoTipo' => MovimientoTesoreria::CUENTA_BANCARIA,
                'CuentaDestinoID' => $cuenta->CuentaTesoreriaID,
                'CuentaDestinoNombre' => $cuenta->NombreCompleto,
                'Monto' => $montoInicial,
                'FechaContable' => $fechaContable,
                'FechaMovimiento' => now(),
                'Concepto' => 'Apertura de cuenta bancaria',
                'Observaciones' => $data['Observaciones'] ?? null,
                'UsuarioID' => $usuarioId,
                'SaldoAnteriorDestino' => 0,
                'SaldoNuevoDestino' => $montoInicial,
            ]);

            return $cuenta;
        });
    }

    public function actualizarCuenta(CuentaTesoreria $cuenta, array $data): CuentaTesoreria
    {
        if (($data['Estado'] ?? $cuenta->Estado) === CuentaTesoreria::ESTADO_INACTIVA && (float) $cuenta->SaldoActual !== 0.0) {
            throw ValidationException::withMessages([
                'Estado' => 'No se puede inactivar una cuenta con saldo distinto de cero.',
            ]);
        }

        $cuenta->fill([
            'Banco' => trim($data['Banco']),
            'NumeroCuenta' => trim($data['NumeroCuenta']),
            'Estado' => $data['Estado'],
        ]);
        $cuenta->save();

        return $cuenta;
    }

    public function transferir(array $data, int $usuarioId): MovimientoTesoreria
    {
        return DB::transaction(fn () => $this->ejecutarMovimiento($data, $usuarioId, MovimientoTesoreria::TIPO_TRANSFERENCIA));
    }

    public function extornar(MovimientoTesoreria $original, array $data, int $usuarioId): MovimientoTesoreria
    {
        if ($original->Tipo !== MovimientoTesoreria::TIPO_TRANSFERENCIA || $original->MovimientoOriginalID) {
            throw ValidationException::withMessages([
                'movimiento' => 'Solo se pueden extornar transferencias originales.',
            ]);
        }

        if ($original->extorno()->exists()) {
            throw ValidationException::withMessages([
                'movimiento' => 'Esta transferencia ya cuenta con un extorno.',
            ]);
        }

        return DB::transaction(function () use ($original, $data, $usuarioId) {
            return $this->ejecutarMovimiento([
                'CuentaOrigen' => $this->referenciaDesdeMovimiento($original->DestinoTipo, $original->CuentaDestinoID),
                'CuentaDestino' => $this->referenciaDesdeMovimiento($original->OrigenTipo, $original->CuentaOrigenID),
                'Monto' => $original->Monto,
                'FechaContable' => $data['FechaContable'] ?? now()->toDateString(),
                'Concepto' => $data['Concepto'] ?? "Extorno de transferencia #{$original->MovimientoTesoreriaID}",
                'Observaciones' => $data['Observaciones'] ?? null,
                'MovimientoOriginalID' => $original->MovimientoTesoreriaID,
            ], $usuarioId, MovimientoTesoreria::TIPO_EXTORNO);
        });
    }

    public function descomponerReferencia(string|int $referencia): array
    {
        $resuelta = $this->resolverReferencia($referencia);

        return [
            'OrigenTesoreriaTipo' => $resuelta['es_caja']
                ? MovimientoTesoreria::CAJA_GERENCIA
                : MovimientoTesoreria::CUENTA_BANCARIA,
            'CuentaTesoreriaID' => $resuelta['cuenta_id'],
        ];
    }

    public function referenciaDocumento(?string $tipo, ?int $cuentaTesoreriaId): string|int|null
    {
        if ($tipo === MovimientoTesoreria::CAJA_GERENCIA) {
            return self::CAJA_GERENCIA_KEY;
        }

        if ($tipo === MovimientoTesoreria::CUENTA_BANCARIA && $cuentaTesoreriaId) {
            return $cuentaTesoreriaId;
        }

        return null;
    }

    public function registrarEgresoDocumento(array $data, int $usuarioId): MovimientoTesoreria
    {
        return DB::transaction(fn () => $this->ejecutarEgresoDocumento(
            $data,
            $usuarioId,
            $this->tipoMovimientoDocumento($data['TipoDocumento'], 'EGRESO')
        ));
    }

    public function ajustarEgresoDocumento(array $data, int $usuarioId): array
    {
        return DB::transaction(function () use ($data, $usuarioId): array {
            $origenAnterior = $data['OrigenAnterior'] ?? null;
            $origenNuevo = $data['OrigenNuevo'] ?? null;
            $montoAnterior = round((float) ($data['MontoAnterior'] ?? 0), 2);
            $montoNuevo = round((float) ($data['MontoNuevo'] ?? 0), 2);
            $movimientos = [];
            $tipoMovimiento = $this->tipoMovimientoDocumento($data['TipoDocumento'], 'AJUSTE');

            if ((string) $origenAnterior === (string) $origenNuevo) {
                $delta = round($montoNuevo - $montoAnterior, 2);
                if ($delta > 0) {
                    $movimientos[] = $this->ejecutarEgresoDocumento(
                        array_merge($data, ['Origen' => $origenNuevo, 'Monto' => $delta]),
                        $usuarioId,
                        $tipoMovimiento
                    );
                } elseif ($delta < 0) {
                    $movimientos[] = $this->ejecutarReintegroDocumento(
                        array_merge($data, ['Origen' => $origenAnterior, 'Monto' => abs($delta)]),
                        $usuarioId,
                        $tipoMovimiento
                    );
                }

                return $movimientos;
            }

            if ($origenAnterior !== null && $montoAnterior > 0) {
                $movimientos[] = $this->ejecutarReintegroDocumento(
                    array_merge($data, ['Origen' => $origenAnterior, 'Monto' => $montoAnterior]),
                    $usuarioId,
                    $tipoMovimiento
                );
            }
            if ($origenNuevo !== null && $montoNuevo > 0) {
                $movimientos[] = $this->ejecutarEgresoDocumento(
                    array_merge($data, ['Origen' => $origenNuevo, 'Monto' => $montoNuevo]),
                    $usuarioId,
                    $tipoMovimiento
                );
            }

            return $movimientos;
        });
    }

    public function revertirEgresoDocumento(array $data, int $usuarioId): MovimientoTesoreria
    {
        return DB::transaction(fn () => $this->ejecutarReintegroDocumento(
            $data,
            $usuarioId,
            $this->tipoMovimientoDocumento($data['TipoDocumento'], 'EXTORNO')
        ));
    }

    public function opcionesCuentas(): array
    {
        $opciones = [self::CAJA_GERENCIA_KEY => 'Caja Abierta - Gerencia'];

        foreach (CuentaTesoreria::where('Estado', CuentaTesoreria::ESTADO_ACTIVA)->orderBy('Banco')->orderBy('NumeroCuenta')->get() as $cuenta) {
            $opciones[(string) $cuenta->CuentaTesoreriaID] = $cuenta->NombreCompleto;
        }

        return $opciones;
    }

    private function ejecutarMovimiento(array $data, int $usuarioId, string $tipo): MovimientoTesoreria
    {
        $monto = $this->normalizarMonto($data['Monto']);
        $fechaContable = $this->resolverFechaContable($data['FechaContable']);
        $origen = $this->resolverReferencia($data['CuentaOrigen']);
        $destino = $this->resolverReferencia($data['CuentaDestino']);

        if ($origen['key'] === $destino['key']) {
            throw ValidationException::withMessages(['CuentaDestino' => 'La cuenta origen y destino deben ser distintas.']);
        }

        $cuentasIds = collect([$origen['cuenta_id'], $destino['cuenta_id']])->filter()->unique()->sort()->values();
        $cuentas = CuentaTesoreria::lockForUpdate()
            ->whereIn('CuentaTesoreriaID', $cuentasIds)
            ->get()
            ->keyBy('CuentaTesoreriaID');

        $fondoGerencia = null;
        if ($origen['es_caja'] || $destino['es_caja']) {
            $fondoGerencia = $this->obtenerFondoGerenciaBloqueado();
        }

        $origen = $this->hidratarCuenta($origen, $cuentas, $fondoGerencia);
        $destino = $this->hidratarCuenta($destino, $cuentas, $fondoGerencia);

        if ($origen['saldo'] < $monto) {
            throw ValidationException::withMessages([
                'Monto' => 'Saldo insuficiente en la cuenta origen. Disponible: S/ '.number_format($origen['saldo'], 2),
            ]);
        }

        $saldoOrigenNuevo = round($origen['saldo'] - $monto, 2);
        $saldoDestinoNuevo = round($destino['saldo'] + $monto, 2);
        $ahora = now();

        $this->guardarSaldo($origen, $saldoOrigenNuevo, $ahora);
        $this->guardarSaldo($destino, $saldoDestinoNuevo, $ahora);

        return MovimientoTesoreria::create([
            'Tipo' => $tipo,
            'OrigenTipo' => $origen['tipo'],
            'CuentaOrigenID' => $origen['cuenta_id'],
            'CuentaOrigenNombre' => $origen['nombre'],
            'DestinoTipo' => $destino['tipo'],
            'CuentaDestinoID' => $destino['cuenta_id'],
            'CuentaDestinoNombre' => $destino['nombre'],
            'Monto' => $monto,
            'FechaContable' => $fechaContable,
            'FechaMovimiento' => $ahora,
            'Concepto' => trim($data['Concepto']),
            'Observaciones' => $data['Observaciones'] ?? null,
            'UsuarioID' => $usuarioId,
            'MovimientoOriginalID' => $data['MovimientoOriginalID'] ?? null,
            'SaldoAnteriorOrigen' => $origen['saldo'],
            'SaldoNuevoOrigen' => $saldoOrigenNuevo,
            'SaldoAnteriorDestino' => $destino['saldo'],
            'SaldoNuevoDestino' => $saldoDestinoNuevo,
        ]);
    }

    private function ejecutarEgresoDocumento(
        array $data,
        int $usuarioId,
        string $tipoMovimiento
    ): MovimientoTesoreria {
        $monto = $this->normalizarMonto($data['Monto']);
        $fechaContable = $this->resolverFechaContable($data['FechaContable']);
        $origen = $this->resolverReferencia($data['Origen']);
        $cuentas = $origen['cuenta_id']
            ? CuentaTesoreria::lockForUpdate()
                ->whereKey($origen['cuenta_id'])
                ->get()
                ->keyBy('CuentaTesoreriaID')
            : collect();
        $fondoGerencia = $origen['es_caja'] ? $this->obtenerFondoGerenciaBloqueado() : null;
        $origen = $this->hidratarCuenta($origen, $cuentas, $fondoGerencia);

        if ($origen['saldo'] < $monto) {
            throw ValidationException::withMessages([
                'OrigenTesoreria' => 'Saldo insuficiente en '.$origen['nombre']
                    .'. Disponible: S/ '.number_format($origen['saldo'], 2),
            ]);
        }

        $saldoNuevo = round($origen['saldo'] - $monto, 2);
        $ahora = now();
        $this->guardarSaldo($origen, $saldoNuevo, $ahora);
        $documento = $this->datosDocumento($data['TipoDocumento'], (int) $data['DocumentoID']);

        return MovimientoTesoreria::create(array_merge([
            'Tipo' => $tipoMovimiento,
            'OrigenTipo' => $origen['tipo'],
            'CuentaOrigenID' => $origen['cuenta_id'],
            'CuentaOrigenNombre' => $origen['nombre'],
            'DestinoTipo' => $documento['tipo'],
            'CuentaDestinoNombre' => $documento['nombre'],
            'Monto' => $monto,
            'FechaContable' => $fechaContable,
            'FechaMovimiento' => $ahora,
            'Concepto' => trim($data['Concepto']),
            'Observaciones' => $data['Observaciones'] ?? null,
            'UsuarioID' => $usuarioId,
            'SaldoAnteriorOrigen' => $origen['saldo'],
            'SaldoNuevoOrigen' => $saldoNuevo,
        ], $documento['vinculo']));
    }

    private function ejecutarReintegroDocumento(
        array $data,
        int $usuarioId,
        string $tipoMovimiento
    ): MovimientoTesoreria {
        $monto = $this->normalizarMonto($data['Monto']);
        $fechaContable = $this->resolverFechaContable($data['FechaContable']);
        $destino = $this->resolverReferencia($data['Origen']);
        $cuentas = $destino['cuenta_id']
            ? CuentaTesoreria::lockForUpdate()
                ->whereKey($destino['cuenta_id'])
                ->get()
                ->keyBy('CuentaTesoreriaID')
            : collect();
        $fondoGerencia = $destino['es_caja'] ? $this->obtenerFondoGerenciaBloqueado() : null;
        $destino = $this->hidratarCuenta($destino, $cuentas, $fondoGerencia, false);
        $saldoNuevo = round($destino['saldo'] + $monto, 2);
        $ahora = now();
        $this->guardarSaldo($destino, $saldoNuevo, $ahora);
        $documento = $this->datosDocumento($data['TipoDocumento'], (int) $data['DocumentoID']);

        return MovimientoTesoreria::create(array_merge([
            'Tipo' => $tipoMovimiento,
            'OrigenTipo' => $documento['tipo'],
            'CuentaOrigenNombre' => $documento['nombre'],
            'DestinoTipo' => $destino['tipo'],
            'CuentaDestinoID' => $destino['cuenta_id'],
            'CuentaDestinoNombre' => $destino['nombre'],
            'Monto' => $monto,
            'FechaContable' => $fechaContable,
            'FechaMovimiento' => $ahora,
            'Concepto' => trim($data['Concepto']),
            'Observaciones' => $data['Observaciones'] ?? null,
            'UsuarioID' => $usuarioId,
            'SaldoAnteriorDestino' => $destino['saldo'],
            'SaldoNuevoDestino' => $saldoNuevo,
        ], $documento['vinculo']));
    }

    private function resolverReferencia(string|int $referencia): array
    {
        if ((string) $referencia === self::CAJA_GERENCIA_KEY) {
            return ['key' => self::CAJA_GERENCIA_KEY, 'es_caja' => true, 'cuenta_id' => null];
        }

        if (! ctype_digit((string) $referencia)) {
            throw ValidationException::withMessages(['CuentaOrigen' => 'La cuenta seleccionada no es valida.']);
        }

        return ['key' => 'BANCO:'.$referencia, 'es_caja' => false, 'cuenta_id' => (int) $referencia];
    }

    private function referenciaDesdeMovimiento(string $tipo, ?int $cuentaId): string|int
    {
        return $tipo === MovimientoTesoreria::CAJA_GERENCIA ? self::CAJA_GERENCIA_KEY : $cuentaId;
    }

    private function hidratarCuenta(
        array $referencia,
        $cuentas,
        ?FondoSede $fondoGerencia,
        bool $requiereActiva = true
    ): array {
        if ($referencia['es_caja']) {
            if (! $fondoGerencia) {
                throw ValidationException::withMessages(['CuentaOrigen' => 'No se encontro la Caja Abierta de Gerencia.']);
            }

            return $referencia + [
                'tipo' => MovimientoTesoreria::CAJA_GERENCIA,
                'nombre' => 'Caja Abierta - Gerencia',
                'saldo' => round((float) $fondoGerencia->Saldo, 2),
                'modelo' => $fondoGerencia,
            ];
        }

        $cuenta = $cuentas->get($referencia['cuenta_id']);
        if (! $cuenta || ($requiereActiva && $cuenta->Estado !== CuentaTesoreria::ESTADO_ACTIVA)) {
            throw ValidationException::withMessages(['CuentaOrigen' => 'La cuenta bancaria seleccionada no esta activa.']);
        }

        return $referencia + [
            'tipo' => MovimientoTesoreria::CUENTA_BANCARIA,
            'nombre' => $cuenta->NombreCompleto,
            'saldo' => round((float) $cuenta->SaldoActual, 2),
            'modelo' => $cuenta,
        ];
    }

    private function guardarSaldo(array $cuenta, float $saldo, $fecha): void
    {
        if ($cuenta['es_caja']) {
            $cuenta['modelo']->Saldo = $saldo;
            $cuenta['modelo']->updated_at = $fecha;
            $cuenta['modelo']->save();

            return;
        }

        $cuenta['modelo']->SaldoActual = $saldo;
        $cuenta['modelo']->FechaUltimoMovimiento = $fecha;
        $cuenta['modelo']->save();
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

    private function normalizarMonto(mixed $monto): float
    {
        $montoNormalizado = round((float) $monto, 2);
        if ($montoNormalizado <= 0) {
            throw ValidationException::withMessages(['Monto' => 'El monto debe ser mayor a cero.']);
        }

        return $montoNormalizado;
    }

    private function tipoMovimientoDocumento(string $tipoDocumento, string $operacion): string
    {
        return match ([$tipoDocumento, $operacion]) {
            [MovimientoTesoreria::COMPRA, 'EGRESO'] => MovimientoTesoreria::TIPO_EGRESO_COMPRA,
            [MovimientoTesoreria::COMPRA, 'AJUSTE'] => MovimientoTesoreria::TIPO_AJUSTE_COMPRA,
            [MovimientoTesoreria::COMPRA, 'EXTORNO'] => MovimientoTesoreria::TIPO_EXTORNO_COMPRA,
            [MovimientoTesoreria::GASTO, 'EGRESO'] => MovimientoTesoreria::TIPO_EGRESO_GASTO,
            [MovimientoTesoreria::GASTO, 'AJUSTE'] => MovimientoTesoreria::TIPO_AJUSTE_GASTO,
            [MovimientoTesoreria::GASTO, 'EXTORNO'] => MovimientoTesoreria::TIPO_EXTORNO_GASTO,
            default => throw ValidationException::withMessages([
                'TipoDocumento' => 'El tipo de documento de Tesoreria no es valido.',
            ]),
        };
    }

    private function datosDocumento(string $tipoDocumento, int $documentoId): array
    {
        return match ($tipoDocumento) {
            MovimientoTesoreria::COMPRA => [
                'tipo' => MovimientoTesoreria::COMPRA,
                'nombre' => "Compra #{$documentoId}",
                'vinculo' => ['CompraID' => $documentoId],
            ],
            MovimientoTesoreria::GASTO => [
                'tipo' => MovimientoTesoreria::GASTO,
                'nombre' => "Gasto #{$documentoId}",
                'vinculo' => ['GastoID' => $documentoId],
            ],
            default => throw ValidationException::withMessages([
                'TipoDocumento' => 'El tipo de documento de Tesoreria no es valido.',
            ]),
        };
    }
}
