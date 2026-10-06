<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AjustarRemesasChiclayoSeptiembre2026 extends Command
{
    protected $signature = 'fondos:ajustar-remesas-chiclayo-septiembre-2026
        {--aplicar : Aplica los montos solicitados; sin esta opción solo muestra una vista previa}';

    protected $description = 'Ajusta cuatro remesas específicas de Chiclayo a Gerencia y sus saldos históricos.';

    private const SEDE_CHICLAYO = 1;

    private const SEDE_GERENCIA = 3;

    private const CORRECCIONES = [
        [
            'transferencia_id' => 251,
            'fecha' => '2026-09-04',
            'monto_actual' => 10739.00,
            'monto_nuevo' => 10369.00,
            'movimiento_origen_id' => 37672,
            'movimiento_destino_id' => 37673,
        ],
        [
            'transferencia_id' => 263,
            'fecha' => '2026-09-10',
            'monto_actual' => 10479.00,
            'monto_nuevo' => 10480.60,
            'movimiento_origen_id' => 40577,
            'movimiento_destino_id' => 40578,
        ],
        [
            'transferencia_id' => 267,
            'fecha' => '2026-09-12',
            'monto_actual' => 7314.00,
            'monto_nuevo' => 7299.00,
            'movimiento_origen_id' => 41747,
            'movimiento_destino_id' => 41748,
        ],
        [
            'transferencia_id' => 300,
            'fecha' => '2026-09-23',
            'monto_actual' => 8568.50,
            'monto_nuevo' => 8588.50,
            'movimiento_origen_id' => 46865,
            'movimiento_destino_id' => 46866,
        ],
    ];

    public function handle(): int
    {
        try {
            if ($this->option('aplicar')) {
                if (! $this->confirm('Se actualizarán únicamente estas cuatro remesas y sus saldos derivados. ¿Desea continuar?', false)) {
                    $this->warn('Operación cancelada. No se modificó ningún registro.');

                    return self::SUCCESS;
                }

                $resultado = DB::transaction(fn (): array => $this->prepararYAplicar(), 3);
            } else {
                $resultado = $this->prepararPlan();
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Fecha', 'Transferencia', 'Monto actual', 'Monto solicitado', 'Diferencia'],
            array_map(fn (array $fila): array => [
                $fila['fecha'],
                '#'.$fila['transferencia_id'],
                $this->formatearMonto($fila['monto_actual_cents']),
                $this->formatearMonto($fila['monto_nuevo_cents']),
                $this->formatearMonto($fila['monto_nuevo_cents'] - $fila['monto_actual_cents']),
            ], $resultado['remesas'])
        );

        $this->table(
            ['Saldo', 'Actual', 'Variación', 'Proyectado'],
            [
                [
                    'Caja abierta Chiclayo',
                    $this->formatearMonto($resultado['fondos'][self::SEDE_CHICLAYO]['actual_cents']),
                    $this->formatearMonto($resultado['fondos'][self::SEDE_CHICLAYO]['delta_cents']),
                    $this->formatearMonto($resultado['fondos'][self::SEDE_CHICLAYO]['proyectado_cents']),
                ],
                [
                    'Caja abierta Gerencia',
                    $this->formatearMonto($resultado['fondos'][self::SEDE_GERENCIA]['actual_cents']),
                    $this->formatearMonto($resultado['fondos'][self::SEDE_GERENCIA]['delta_cents']),
                    $this->formatearMonto($resultado['fondos'][self::SEDE_GERENCIA]['proyectado_cents']),
                ],
            ]
        );

        $this->line('Snapshots de caja abierta que se actualizarían: '.$resultado['snapshots_actualizados']);
        $this->line('SaldoCajaChica no se modifica.');

        if (! $resultado['aplicado']) {
            $this->warn('Vista previa solamente. Revise los datos y, para aplicar, ejecute nuevamente con --aplicar.');

            return self::SUCCESS;
        }

        $this->info('Ajuste aplicado correctamente dentro de una transacción.');

        return self::SUCCESS;
    }

    private function prepararYAplicar(): array
    {
        $fondos = DB::table('fondo_sedes')
            ->whereIn('SedeID', [self::SEDE_CHICLAYO, self::SEDE_GERENCIA])
            ->orderBy('SedeID')
            ->lockForUpdate()
            ->get()
            ->keyBy('SedeID');

        $plan = $this->prepararPlan(lockRows: true, fondosBloqueados: $fondos);
        $ahora = now();

        foreach ($plan['remesas'] as $remesa) {
            DB::table('transferencia_sedes')
                ->where('TransferenciaID', $remesa['transferencia_id'])
                ->update([
                    'Monto' => $remesa['monto_nuevo_cents'] / 100,
                    'MontoAprobado' => $remesa['monto_nuevo_cents'] / 100,
                    'updated_at' => $ahora,
                ]);
        }

        foreach ($plan['actualizaciones_movimientos'] as $movimientoId => $actualizacion) {
            $datos = [
                'SaldoAnterior' => $actualizacion['saldo_anterior_cents'] === null
                    ? null
                    : $actualizacion['saldo_anterior_cents'] / 100,
                'SaldoNuevo' => $actualizacion['saldo_nuevo_cents'] === null
                    ? null
                    : $actualizacion['saldo_nuevo_cents'] / 100,
                'updated_at' => $ahora,
            ];

            if (isset($plan['montos_movimientos'][$movimientoId])) {
                $datos['Monto'] = $plan['montos_movimientos'][$movimientoId] / 100;
            }

            DB::table('movimientos_fondo')->where('MovimientoID', $movimientoId)->update($datos);
        }

        foreach ($plan['fondos'] as $sedeId => $fondo) {
            DB::table('fondo_sedes')->where('SedeID', $sedeId)->update([
                'Saldo' => $fondo['proyectado_cents'] / 100,
                'updated_at' => $ahora,
            ]);
        }

        $plan['aplicado'] = true;

        return $plan;
    }

    private function prepararPlan(bool $lockRows = false, $fondosBloqueados = null): array
    {
        $sedes = DB::table('Sede')->whereIn('SedeID', [self::SEDE_CHICLAYO, self::SEDE_GERENCIA])->get()->keyBy('SedeID');

        if (
            $sedes->count() !== 2
            || stripos((string) $sedes->get(self::SEDE_CHICLAYO)?->Nombre, 'Chiclayo') === false
            || stripos((string) $sedes->get(self::SEDE_GERENCIA)?->Nombre, 'Gerencia') === false
        ) {
            throw new RuntimeException('Los SedeID 1 y 3 no corresponden a Chiclayo y Gerencia. No se hicieron cambios.');
        }

        $fondos = $fondosBloqueados ?? DB::table('fondo_sedes')
            ->whereIn('SedeID', [self::SEDE_CHICLAYO, self::SEDE_GERENCIA])
            ->get()
            ->keyBy('SedeID');

        if ($fondos->count() !== 2) {
            throw new RuntimeException('No se encontraron los fondos actuales de Chiclayo y Gerencia.');
        }

        $idsTransferencias = array_column(self::CORRECCIONES, 'transferencia_id');
        $transferenciasQuery = DB::table('transferencia_sedes')->whereIn('TransferenciaID', $idsTransferencias)->orderBy('TransferenciaID');
        if ($lockRows) {
            $transferenciasQuery->lockForUpdate();
        }
        $transferencias = $transferenciasQuery->get()->keyBy('TransferenciaID');

        if ($transferencias->count() !== count(self::CORRECCIONES)) {
            throw new RuntimeException('Falta alguna de las cuatro transferencias esperadas. No se hicieron cambios.');
        }

        $movimientosObjetivoIds = [];
        $montosMovimientos = [];
        $eventosDelta = [self::SEDE_CHICLAYO => [], self::SEDE_GERENCIA => []];
        $remesas = [];

        foreach (self::CORRECCIONES as $correccion) {
            $transferencia = $transferencias->get($correccion['transferencia_id']);
            $montoActualCents = $this->aCentimos($correccion['monto_actual']);
            $montoNuevoCents = $this->aCentimos($correccion['monto_nuevo']);

            if (
                (int) $transferencia->SedeOrigenID !== self::SEDE_CHICLAYO
                || (int) $transferencia->SedeDestinoID !== self::SEDE_GERENCIA
                || $transferencia->Estado !== 'ACEPTADO'
                || ($transferencia->CuentaOrigen ?? 'CAJA_ABIERTA') !== 'CAJA_ABIERTA'
                || ($transferencia->CuentaDestino ?? 'CAJA_ABIERTA') !== 'CAJA_ABIERTA'
                || (bool) $transferencia->EsSolicitudCapital
                || (bool) $transferencia->EsSolicitudGerencia
                || $this->aCentimos($transferencia->Monto) !== $montoActualCents
                || $this->aCentimos($transferencia->MontoAprobado) !== $montoActualCents
                || substr((string) $transferencia->FechaTransferencia, 0, 10) !== $correccion['fecha']
            ) {
                throw new RuntimeException("La transferencia #{$correccion['transferencia_id']} ya no coincide con lo revisado. No se hicieron cambios.");
            }

            $movimientosObjetivoIds[] = $correccion['movimiento_origen_id'];
            $movimientosObjetivoIds[] = $correccion['movimiento_destino_id'];
            $remesas[] = [
                'transferencia_id' => $correccion['transferencia_id'],
                'fecha' => $correccion['fecha'],
                'monto_actual_cents' => $montoActualCents,
                'monto_nuevo_cents' => $montoNuevoCents,
            ];
        }

        $movimientosTransferenciaQuery = DB::table('movimientos_fondo')
            ->whereIn('TransferenciaID', $idsTransferencias)
            ->orderBy('MovimientoID');
        if ($lockRows) {
            $movimientosTransferenciaQuery->lockForUpdate();
        }
        $movimientosTransferencia = $movimientosTransferenciaQuery->get();

        if ($movimientosTransferencia->count() !== count($movimientosObjetivoIds)) {
            throw new RuntimeException('Las transferencias no tienen exactamente los ocho movimientos revisados. No se hicieron cambios.');
        }

        $movimientosPorId = $movimientosTransferencia->keyBy('MovimientoID');

        foreach (self::CORRECCIONES as $correccion) {
            $montoActualCents = $this->aCentimos($correccion['monto_actual']);
            $montoNuevoCents = $this->aCentimos($correccion['monto_nuevo']);
            $deltaOrigen = $montoActualCents - $montoNuevoCents;
            $deltaDestino = $montoNuevoCents - $montoActualCents;

            $this->validarMovimientoObjetivo(
                $movimientosPorId->get($correccion['movimiento_origen_id']),
                $correccion,
                self::SEDE_CHICLAYO,
                'ENVIO_TRANSFERENCIA',
                -$montoActualCents,
            );
            $this->validarMovimientoObjetivo(
                $movimientosPorId->get($correccion['movimiento_destino_id']),
                $correccion,
                self::SEDE_GERENCIA,
                'RECEPCION_TRANSFERENCIA',
                $montoActualCents,
            );

            $movimientosOrigen = $movimientosPorId->get($correccion['movimiento_origen_id']);
            $movimientosDestino = $movimientosPorId->get($correccion['movimiento_destino_id']);

            $montosMovimientos[$correccion['movimiento_origen_id']] = -$montoNuevoCents;
            $montosMovimientos[$correccion['movimiento_destino_id']] = $montoNuevoCents;
            $eventosDelta[self::SEDE_CHICLAYO][] = [
                'fecha' => (string) $movimientosOrigen->FechaMovimiento,
                'movimiento_id' => (int) $movimientosOrigen->MovimientoID,
                'delta_cents' => $deltaOrigen,
            ];
            $eventosDelta[self::SEDE_GERENCIA][] = [
                'fecha' => (string) $movimientosDestino->FechaMovimiento,
                'movimiento_id' => (int) $movimientosDestino->MovimientoID,
                'delta_cents' => $deltaDestino,
            ];
        }

        $fechaDesde = min(array_column(self::CORRECCIONES, 'fecha')).' 00:00:00';
        $movimientosLedgerQuery = DB::table('movimientos_fondo')
            ->whereIn('SedeID', [self::SEDE_CHICLAYO, self::SEDE_GERENCIA])
            ->where('FechaMovimiento', '>=', $fechaDesde)
            ->orderBy('FechaMovimiento')
            ->orderBy('MovimientoID');
        if ($lockRows) {
            $movimientosLedgerQuery->lockForUpdate();
        }
        $movimientosLedger = $movimientosLedgerQuery->get();

        $transferenciaIdsLedger = $movimientosLedger->pluck('TransferenciaID')->filter()->unique()->values();
        $transferenciasLedgerQuery = DB::table('transferencia_sedes')->whereIn('TransferenciaID', $transferenciaIdsLedger)->orderBy('TransferenciaID');
        if ($lockRows) {
            $transferenciasLedgerQuery->lockForUpdate();
        }
        $transferenciasLedger = $transferenciasLedgerQuery->get()->keyBy('TransferenciaID');

        $actualizacionesMovimientos = [];
        foreach ($movimientosLedger as $movimiento) {
            $sedeId = (int) $movimiento->SedeID;
            if (! $this->afectaCajaAbierta($movimiento, $transferenciasLedger->get($movimiento->TransferenciaID))) {
                continue;
            }

            $deltaAnterior = 0;
            $deltaPosterior = 0;
            foreach ($eventosDelta[$sedeId] as $evento) {
                $comparacion = $this->compararOrden(
                    $evento['fecha'],
                    $evento['movimiento_id'],
                    (string) $movimiento->FechaMovimiento,
                    (int) $movimiento->MovimientoID,
                );
                if ($comparacion < 0) {
                    $deltaAnterior += $evento['delta_cents'];
                    $deltaPosterior += $evento['delta_cents'];
                } elseif ($comparacion === 0) {
                    $deltaPosterior += $evento['delta_cents'];
                }
            }

            if ($deltaAnterior === 0 && $deltaPosterior === 0) {
                continue;
            }

            $actualizacionesMovimientos[(int) $movimiento->MovimientoID] = [
                'saldo_anterior_cents' => $movimiento->SaldoAnterior === null
                    ? null
                    : $this->aCentimos($movimiento->SaldoAnterior) + $deltaAnterior,
                'saldo_nuevo_cents' => $movimiento->SaldoNuevo === null
                    ? null
                    : $this->aCentimos($movimiento->SaldoNuevo) + $deltaPosterior,
            ];
        }

        $fondosResultado = [];
        foreach ([self::SEDE_CHICLAYO, self::SEDE_GERENCIA] as $sedeId) {
            $deltaTotal = array_sum(array_column($eventosDelta[$sedeId], 'delta_cents'));
            $saldoActualCents = $this->aCentimos($fondos->get($sedeId)->Saldo);
            $fondosResultado[$sedeId] = [
                'actual_cents' => $saldoActualCents,
                'delta_cents' => $deltaTotal,
                'proyectado_cents' => $saldoActualCents + $deltaTotal,
            ];
        }

        return [
            'aplicado' => false,
            'remesas' => $remesas,
            'fondos' => $fondosResultado,
            'snapshots_actualizados' => count($actualizacionesMovimientos),
            'actualizaciones_movimientos' => $actualizacionesMovimientos,
            'montos_movimientos' => $montosMovimientos,
        ];
    }

    private function validarMovimientoObjetivo(?object $movimiento, array $correccion, int $sedeId, string $tipo, int $montoCents): void
    {
        if (
            ! $movimiento
            || (int) $movimiento->SedeID !== $sedeId
            || (int) $movimiento->TransferenciaID !== $correccion['transferencia_id']
            || $movimiento->Tipo !== $tipo
            || $this->aCentimos($movimiento->Monto) !== $montoCents
            || substr((string) $movimiento->FechaMovimiento, 0, 10) !== $correccion['fecha']
        ) {
            throw new RuntimeException("El movimiento esperado para la transferencia #{$correccion['transferencia_id']} no coincide. No se hicieron cambios.");
        }
    }

    private function afectaCajaAbierta(object $movimiento, ?object $transferencia): bool
    {
        if (! $transferencia) {
            return ! in_array($movimiento->Tipo, [
                'INGRESO_CAJA_CHICA',
                'EGRESO_CAJA_CHICA',
                'TRASLADO_CC_A_CA',
            ], true);
        }

        if ($transferencia->EsSolicitudGerencia || $transferencia->EsSolicitudCapital) {
            return true;
        }

        $sedeId = (int) $movimiento->SedeID;
        if ($sedeId === (int) $transferencia->SedeOrigenID) {
            return ($transferencia->CuentaOrigen ?? 'CAJA_ABIERTA') !== 'CAJA_CHICA';
        }
        if ($sedeId === (int) $transferencia->SedeDestinoID) {
            return ($transferencia->CuentaDestino ?? 'CAJA_ABIERTA') !== 'CAJA_CHICA';
        }

        return false;
    }

    private function compararOrden(string $fechaA, int $idA, string $fechaB, int $idB): int
    {
        $fecha = strcmp($fechaA, $fechaB);

        return $fecha !== 0 ? $fecha : ($idA <=> $idB);
    }

    private function aCentimos(mixed $monto): int
    {
        return (int) round((float) $monto * 100);
    }

    private function formatearMonto(int $centimos): string
    {
        return 'S/ '.number_format($centimos / 100, 2, '.', ',');
    }
}
