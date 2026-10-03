<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CuentaTesoreria;
use App\Models\FondoSede;
use App\Models\Gasto;
use App\Models\MovimientoFondo;
use App\Models\MovimientoTesoreria;
use App\Models\Sede;
use App\Models\TransferenciaSede;
use Carbon\Carbon;

class GerenciaBalanceDiarioService
{
    public function generar(string $fecha): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $sedeGerencia = Sede::query()
            ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
            ->orderBy('SedeID')
            ->firstOrFail();

        $movimientos = MovimientoTesoreria::query()
            ->with(['gasto.motivo', 'compra.proveedor'])
            ->orderBy('FechaContable')
            ->orderBy('MovimientoTesoreriaID')
            ->get();

        $movimientosFondo = MovimientoFondo::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->orderBy('FechaMovimiento')
            ->orderBy('MovimientoID')
            ->get();

        $transferenciaIds = $movimientosFondo->pluck('TransferenciaID')->filter()->unique();
        $transferencias = $transferenciaIds->isEmpty()
            ? collect()
            : TransferenciaSede::withoutGlobalScopes()
                ->whereIn('TransferenciaID', $transferenciaIds)
                ->with(['sedeOrigen', 'sedeDestino'])
                ->get()
                ->keyBy('TransferenciaID');

        $movimientosFondo->each(function (MovimientoFondo $movimiento) use ($transferencias): void {
            $movimiento->setRelation('transferencia', $transferencias->get($movimiento->TransferenciaID));
        });

        $fondo = FondoSede::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->first();

        $gastosVinculados = $movimientos->pluck('GastoID')->filter()->map(fn ($id) => (int) $id)->all();
        $comprasVinculadas = $movimientos->pluck('CompraID')->filter()->map(fn ($id) => (int) $id)->all();

        $gastosAntiguos = Gasto::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->where('Activo', true)
            ->whereIn('OrigenTesoreriaTipo', [MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::CUENTA_BANCARIA])
            ->whereNotIn('GastoID', $gastosVinculados ?: [0])
            ->with('motivo')
            ->get();

        $comprasAntiguas = Compra::withoutGlobalScopes()
            ->where('SedeID', $sedeGerencia->SedeID)
            ->where('Activo', true)
            ->whereIn('OrigenTesoreriaTipo', [MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::CUENTA_BANCARIA])
            ->whereNotIn('CompraID', $comprasVinculadas ?: [0])
            ->with('proveedor')
            ->get();

        $caja = $this->crearEstadoCuenta(
            nombre: 'Caja Gerencia',
            tipo: MovimientoTesoreria::CAJA_GERENCIA,
            cuentaId: null,
            saldoActual: (float) ($fondo?->Saldo ?? 0),
            fecha: $fecha,
            movimientos: $movimientos,
            movimientosFondo: $movimientosFondo,
            transferencias: $transferencias,
            gastosAntiguos: $gastosAntiguos,
            comprasAntiguas: $comprasAntiguas,
            sedeGerenciaId: (int) $sedeGerencia->SedeID,
        );

        $cuentas = CuentaTesoreria::query()
            ->orderBy('Banco')
            ->orderBy('NumeroCuenta')
            ->get()
            ->map(fn (CuentaTesoreria $cuenta): array => $this->crearEstadoCuenta(
                nombre: $cuenta->NombreCompleto,
                tipo: MovimientoTesoreria::CUENTA_BANCARIA,
                cuentaId: (int) $cuenta->CuentaTesoreriaID,
                saldoActual: (float) $cuenta->SaldoActual,
                fecha: $fecha,
                movimientos: $movimientos,
                movimientosFondo: collect(),
                transferencias: $transferencias,
                gastosAntiguos: collect(),
                comprasAntiguas: collect(),
                sedeGerenciaId: (int) $sedeGerencia->SedeID,
            ))
            ->all();

        return [
            'fecha' => Carbon::parse($fecha),
            'sede' => $sedeGerencia,
            'caja' => $caja,
            'cuentas' => $cuentas,
            'generadoEn' => now(),
        ];
    }

    private function crearEstadoCuenta(
        string $nombre,
        string $tipo,
        ?int $cuentaId,
        float $saldoActual,
        string $fecha,
        $movimientos,
        $movimientosFondo,
        $transferencias,
        $gastosAntiguos,
        $comprasAntiguas,
        int $sedeGerenciaId,
    ): array {
        $transacciones = [];

        foreach ($movimientos as $movimiento) {
            $esOrigen = $movimiento->OrigenTipo === $tipo
                && ($tipo === MovimientoTesoreria::CAJA_GERENCIA || (int) $movimiento->CuentaOrigenID === $cuentaId);
            $esDestino = $movimiento->DestinoTipo === $tipo
                && ($tipo === MovimientoTesoreria::CAJA_GERENCIA || (int) $movimiento->CuentaDestinoID === $cuentaId);

            if (! $esOrigen && ! $esDestino) {
                continue;
            }

            $monto = (float) $movimiento->Monto * ($esDestino ? 1 : -1);
            $concepto = $movimiento->Concepto;
            $categoria = 'otros';

            if ($movimiento->GastoID) {
                $categoria = 'gastos';
                $concepto = $movimiento->gasto?->motivo?->Nombre ?: $concepto;
            } elseif ($movimiento->CompraID) {
                $categoria = 'compras';
                $concepto = $movimiento->compra?->proveedor?->Nombre ?: $concepto;
            } elseif (in_array($movimiento->Tipo, [MovimientoTesoreria::TIPO_TRANSFERENCIA, MovimientoTesoreria::TIPO_EXTORNO], true)) {
                $categoria = 'remesa';
            }

            $transacciones[] = [
                'fecha' => Carbon::parse($movimiento->FechaContable)->toDateString(),
                'orden' => $movimiento->FechaMovimiento?->format('Y-m-d H:i:s') ?? $movimiento->created_at?->format('Y-m-d H:i:s') ?? '',
                'id_orden' => (int) $movimiento->MovimientoTesoreriaID,
                'numero' => (string) $movimiento->MovimientoTesoreriaID,
                'categoria' => $categoria,
                'concepto' => $concepto ?: $movimiento->Tipo,
                'contraparte' => $esOrigen ? $movimiento->CuentaDestinoNombre : $movimiento->CuentaOrigenNombre,
                'cuenta' => $nombre,
                'monto' => $monto,
                'saldo_antes' => $esOrigen ? $movimiento->SaldoAnteriorOrigen : $movimiento->SaldoAnteriorDestino,
                'saldo_despues' => $esOrigen ? $movimiento->SaldoNuevoOrigen : $movimiento->SaldoNuevoDestino,
                'es_remesa' => $categoria === 'remesa',
            ];
        }

        foreach ($movimientosFondo as $movimiento) {
            $transferencia = $transferencias->get($movimiento->TransferenciaID);
            $esRemesa = $transferencia !== null;
            if (! $this->movimientoFondoAfectaCajaGerencia($movimiento, $transferencia, $sedeGerenciaId)) {
                continue;
            }

            $contraparte = $transferencia
                ? ((int) $transferencia->SedeOrigenID === $sedeGerenciaId
                    ? $transferencia->sedeDestino?->Nombre
                    : $transferencia->sedeOrigen?->Nombre)
                : null;

            $transacciones[] = [
                'fecha' => Carbon::parse($movimiento->FechaMovimiento)->toDateString(),
                'orden' => $movimiento->FechaMovimiento?->format('Y-m-d H:i:s') ?? '',
                'id_orden' => (int) $movimiento->MovimientoID,
                'numero' => (string) ($movimiento->TransferenciaID ?: $movimiento->MovimientoID),
                'categoria' => $esRemesa ? 'remesa' : 'otros',
                'concepto' => $transferencia ? 'Remesa ' . strtolower(str_replace('_', ' ', $movimiento->Tipo)) : ($movimiento->Observacion ?: $movimiento->Tipo),
                'contraparte' => $contraparte ?: 'Caja Gerencia',
                'cuenta' => 'Caja Abierta',
                'monto' => (float) $movimiento->Monto,
                'saldo_antes' => $movimiento->Tipo === 'TRASLADO_CC_A_CA' ? null : $movimiento->SaldoAnterior,
                'saldo_despues' => $movimiento->Tipo === 'TRASLADO_CC_A_CA' ? null : $movimiento->SaldoNuevo,
                'es_remesa' => $esRemesa,
            ];
        }

        foreach ($gastosAntiguos as $gasto) {
            if (! $this->perteneceACuenta($gasto->OrigenTesoreriaTipo, $gasto->CuentaTesoreriaID, $tipo, $cuentaId)) {
                continue;
            }

            $transacciones[] = $this->crearTransaccionAntigua(
                $gasto->FechaEmision,
                $gasto->GastoID,
                'gastos',
                $gasto->motivo?->Nombre ?: 'Gasto',
                $nombre,
                (float) $gasto->Total,
            );
        }

        foreach ($comprasAntiguas as $compra) {
            if (! $this->perteneceACuenta($compra->OrigenTesoreriaTipo, $compra->CuentaTesoreriaID, $tipo, $cuentaId)) {
                continue;
            }

            $transacciones[] = $this->crearTransaccionAntigua(
                $compra->FechaPago ?? $compra->FechaEmision,
                $compra->CompraID,
                'compras',
                $compra->proveedor?->Nombre ?: 'Compra',
                $nombre,
                (float) $compra->Total,
            );
        }

        usort($transacciones, fn (array $a, array $b): int => [$a['fecha'], $a['orden'], $a['id_orden']] <=> [$b['fecha'], $b['orden'], $b['id_orden']]);

        $dia = array_values(array_filter($transacciones, fn (array $item): bool => $item['fecha'] === $fecha));
        $opening = $this->calcularSaldoInicial($transacciones, $fecha, $saldoActual);
        $closing = $this->calcularSaldoCierre($transacciones, $fecha, $saldoActual);

        $running = $opening;
        foreach ($dia as &$transaccion) {
            $running = round($running + $transaccion['monto'], 2);
            $transaccion['saldo'] = $running;
            $transaccion['ingreso'] = $transaccion['monto'] > 0 ? $transaccion['monto'] : 0;
            $transaccion['salida'] = $transaccion['monto'] < 0 ? abs($transaccion['monto']) : 0;
        }
        unset($transaccion);

        $excedente = round($closing - $running, 2);
        $secciones = [
            'remesas_entrada' => [],
            'remesas_salida' => [],
            'gastos' => [],
            'compras' => [],
            'otros' => [],
        ];

        foreach ($dia as $transaccion) {
            if ($transaccion['categoria'] === 'remesa') {
                $secciones[$transaccion['monto'] >= 0 ? 'remesas_entrada' : 'remesas_salida'][] = $transaccion;
            } else {
                $secciones[$transaccion['categoria']][] = $transaccion;
            }
        }

        return [
            'nombre' => $nombre,
            'saldo_inicial' => round($opening, 2),
            'saldo_cierre' => round($closing, 2),
            'excedente' => $excedente,
            'movimientos' => $dia,
            'secciones' => $secciones,
            'total_ingresos' => round(array_sum(array_column($dia, 'ingreso')), 2),
            'total_salidas' => round(array_sum(array_column($dia, 'salida')), 2),
        ];
    }

    private function crearTransaccionAntigua($fecha, int $id, string $categoria, string $concepto, string $cuenta, float $monto): array
    {
        return [
            'fecha' => Carbon::parse($fecha)->toDateString(),
            'orden' => Carbon::parse($fecha)->format('Y-m-d H:i:s'),
            'id_orden' => $id,
            'numero' => (string) $id,
            'categoria' => $categoria,
            'concepto' => $concepto,
            'contraparte' => $concepto,
            'cuenta' => $cuenta,
            'monto' => -abs($monto),
            'saldo_antes' => null,
            'saldo_despues' => null,
            'es_remesa' => false,
        ];
    }

    private function perteneceACuenta(?string $origenTipo, ?int $origenCuentaId, string $tipo, ?int $cuentaId): bool
    {
        if ($origenTipo !== $tipo) {
            return false;
        }

        return $tipo === MovimientoTesoreria::CAJA_GERENCIA
            || (int) $origenCuentaId === $cuentaId;
    }

    private function movimientoFondoAfectaCajaGerencia(MovimientoFondo $movimiento, ?TransferenciaSede $transferencia, int $sedeGerenciaId): bool
    {
        if ($transferencia) {
            if ($transferencia->EsSolicitudGerencia || $transferencia->EsSolicitudCapital) {
                return true;
            }

            $gerenciaEsOrigen = (int) $transferencia->SedeOrigenID === $sedeGerenciaId;
            $cuenta = $gerenciaEsOrigen ? $transferencia->CuentaOrigen : $transferencia->CuentaDestino;

            return ($cuenta ?? 'CAJA_ABIERTA') !== 'CAJA_CHICA';
        }

        return ! in_array($movimiento->Tipo, [
            'INGRESO_CAJA_CHICA',
            'EGRESO_CAJA_CHICA',
        ], true);
    }

    private function calcularSaldoInicial(array $transacciones, string $fecha, float $saldoActual): float
    {
        $snapshotIndex = null;
        foreach ($transacciones as $index => $transaccion) {
            if ($transaccion['fecha'] < $fecha && $transaccion['saldo_despues'] !== null) {
                $snapshotIndex = $index;
            }
        }

        if ($snapshotIndex !== null) {
            $saldo = (float) $transacciones[$snapshotIndex]['saldo_despues'];
            foreach ($transacciones as $index => $transaccion) {
                if ($index > $snapshotIndex && $transaccion['fecha'] < $fecha) {
                    $saldo += $transaccion['monto'];
                }
            }

            return round($saldo, 2);
        }

        $movimientosDesdeFecha = array_sum(array_map(
            fn (array $transaccion): float => $transaccion['fecha'] >= $fecha ? $transaccion['monto'] : 0,
            $transacciones,
        ));

        return round($saldoActual - $movimientosDesdeFecha, 2);
    }

    private function calcularSaldoCierre(array $transacciones, string $fecha, float $saldoActual): float
    {
        $fechaActual = now()->toDateString();
        if ($fecha === $fechaActual) {
            return round($saldoActual, 2);
        }

        foreach ($transacciones as $transaccion) {
            if ($transaccion['fecha'] > $fecha && $transaccion['saldo_antes'] !== null) {
                return round((float) $transaccion['saldo_antes'], 2);
            }
        }

        $saldoPorFlujos = $saldoActual - array_sum(array_map(
            fn (array $transaccion): float => $transaccion['fecha'] > $fecha ? $transaccion['monto'] : 0,
            $transacciones,
        ));

        return round($saldoPorFlujos, 2);
    }
}
