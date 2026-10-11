<?php

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CorregirFechasContablesGerencia extends Command
{
    protected $signature = 'gerencia:corregir-fechas-contables
                            {--transferencia-id= : ID de la remesa a BCP Ahorros}
                            {--extorno-id= : ID del extorno del gasto 271}
                            {--usuario-id= : Usuario responsable, obligatorio para aplicar}
                            {--aplicar : Cambia únicamente las fechas contables}
                            {--confirmar= : Confirmación literal APLICAR-CORRECCION-GERENCIA-2026-10-01}';

    protected $description = 'Previsualiza o corrige las fechas contables de dos movimientos específicos de Gerencia';

    private const FECHA_ORIGINAL = '2026-10-09';

    private const FECHA_CORRECTA = '2026-10-01';

    private const CUENTA_AHORROS = '305-15479668-0-14';

    private const CONFIRMACION = 'APLICAR-CORRECCION-GERENCIA-2026-10-01';

    public function handle(): int
    {
        try {
            $transferenciaId = $this->idRequerido('transferencia-id');
            $extornoId = $this->idRequerido('extorno-id');

            if ($transferenciaId === $extornoId) {
                throw new RuntimeException('Los IDs de la remesa y del extorno deben ser distintos.');
            }

            if ($this->option('aplicar')) {
                $usuarioId = $this->validarAplicacion();
                $resultado = DB::transaction(fn (): array => $this->aplicar($transferenciaId, $extornoId, $usuarioId));
            } else {
                $resultado = $this->previsualizar($transferenciaId, $extornoId);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Tipo', 'Referencia', 'Fecha contable actual', 'Fecha contable objetivo', 'Monto'],
            $resultado['movimientos'],
        );
        $this->line('No se modifican montos, saldos, cuentas, gastos ni FechaMovimiento.');

        if (! $this->option('aplicar')) {
            $this->warn('Previsualización: no se modificó ningún dato.');
            $this->line('Para aplicar tras validar los IDs y la cuenta, agregue --aplicar --usuario-id=ID --confirmar='.self::CONFIRMACION);

            return self::SUCCESS;
        }

        if ($resultado['actualizados'] === 0) {
            $this->info('Las dos fechas contables ya estaban corregidas; no se modificó ningún dato.');

            return self::SUCCESS;
        }

        $this->info('Fechas contables de Gerencia actualizadas y auditadas. Movimientos modificados: '.$resultado['actualizados'].'.');

        return self::SUCCESS;
    }

    private function idRequerido(string $opcion): int
    {
        $valor = $this->option($opcion);
        if (! is_numeric($valor) || (int) $valor <= 0) {
            throw new RuntimeException("Indique --{$opcion} con un ID numérico válido.");
        }

        return (int) $valor;
    }

    private function validarAplicacion(): int
    {
        $usuarioId = $this->option('usuario-id');
        if (! is_numeric($usuarioId) || (int) $usuarioId <= 0 || ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            throw new RuntimeException('Para aplicar indique --usuario-id con un usuario existente.');
        }

        if ($this->option('confirmar') !== self::CONFIRMACION) {
            throw new RuntimeException('La confirmación no coincide. Debe indicar --confirmar='.self::CONFIRMACION);
        }

        return (int) $usuarioId;
    }

    private function previsualizar(int $transferenciaId, int $extornoId): array
    {
        $movimientos = $this->validarMovimientos($transferenciaId, $extornoId, false);

        return ['movimientos' => $this->filasSalida($movimientos), 'actualizados' => 0];
    }

    private function aplicar(int $transferenciaId, int $extornoId, int $usuarioId): array
    {
        $movimientos = $this->validarMovimientos($transferenciaId, $extornoId, true);
        $sedeId = DB::table('Sede')
            ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
            ->orderBy('SedeID')
            ->value('SedeID');

        if (! $sedeId) {
            throw new RuntimeException('No se encontró la sede Gerencia para registrar la auditoría.');
        }

        $actualizados = 0;
        foreach ($movimientos as $movimiento) {
            if ((string) $movimiento->FechaContable === self::FECHA_CORRECTA) {
                continue;
            }

            $afectados = DB::table('tesoreria_movimientos')
                ->where('MovimientoTesoreriaID', $movimiento->MovimientoTesoreriaID)
                ->where('FechaContable', self::FECHA_ORIGINAL)
                ->update(['FechaContable' => self::FECHA_CORRECTA]);

            if ($afectados !== 1) {
                throw new RuntimeException("El movimiento {$movimiento->MovimientoTesoreriaID} cambió durante la operación; se cancelaron los cambios.");
            }

            $movimiento->FechaContable = self::FECHA_CORRECTA;

            Log::registrar(
                'ACTUALIZAR',
                'MovimientoTesoreria',
                (int) $movimiento->MovimientoTesoreriaID,
                ['FechaContable' => self::FECHA_ORIGINAL],
                ['FechaContable' => self::FECHA_CORRECTA],
                (int) $sedeId,
                $usuarioId,
            );
            $actualizados++;
        }

        return ['movimientos' => $this->filasSalida($movimientos), 'actualizados' => $actualizados];
    }

    private function validarMovimientos(int $transferenciaId, int $extornoId, bool $bloquear): array
    {
        $cuentaId = DB::table('tesoreria_cuentas')
            ->where('NumeroCuenta', self::CUENTA_AHORROS)
            ->value('CuentaTesoreriaID');

        if (! $cuentaId) {
            throw new RuntimeException('No se encontró la cuenta BCP de ahorros 305-15479668-0-14.');
        }

        $transferencia = $this->movimiento($transferenciaId, $bloquear);
        if (! $transferencia
            || $transferencia->Tipo !== 'TRANSFERENCIA'
            || $transferencia->OrigenTipo !== 'CAJA_GERENCIA'
            || $transferencia->DestinoTipo !== 'CUENTA_BANCARIA'
            || (int) $transferencia->CuentaDestinoID !== (int) $cuentaId
            || round((float) $transferencia->Monto, 2) !== 3120.00
            || ! in_array((string) $transferencia->FechaContable, [self::FECHA_ORIGINAL, self::FECHA_CORRECTA], true)) {
            throw new RuntimeException('El movimiento indicado no coincide con la remesa de S/ 3,120 hacia la cuenta BCP de ahorros.');
        }

        $extorno = $this->movimiento($extornoId, $bloquear);
        if (! $extorno
            || $extorno->Tipo !== 'EXTORNO_GASTO'
            || $extorno->OrigenTipo !== 'GASTO'
            || $extorno->DestinoTipo !== 'CAJA_GERENCIA'
            || (int) $extorno->GastoID !== 271
            || round((float) $extorno->Monto, 2) !== 124.37
            || ! in_array((string) $extorno->FechaContable, [self::FECHA_ORIGINAL, self::FECHA_CORRECTA], true)) {
            throw new RuntimeException('El movimiento indicado no coincide con el extorno de S/ 124.37 del gasto #271.');
        }

        $this->validarEgresoGasto(271, $bloquear);
        $this->validarEgresoGasto(272, $bloquear);

        return [$transferencia, $extorno];
    }

    private function validarEgresoGasto(int $gastoId, bool $bloquear): void
    {
        $query = DB::table('tesoreria_movimientos')
            ->where('Tipo', 'EGRESO_GASTO')
            ->where('OrigenTipo', 'CAJA_GERENCIA')
            ->where('DestinoTipo', 'GASTO')
            ->where('GastoID', $gastoId)
            ->where('Monto', 124.37)
            ->where('FechaContable', self::FECHA_CORRECTA);

        if ($bloquear) {
            $query->lockForUpdate();
        }

        if ($query->count() !== 1) {
            throw new RuntimeException("No se encontró exactamente un egreso de S/ 124.37 para el gasto #{$gastoId} con fecha contable 01/10/2026; no se aplicó la corrección.");
        }
    }

    private function movimiento(int $id, bool $bloquear): ?object
    {
        $query = DB::table('tesoreria_movimientos')->where('MovimientoTesoreriaID', $id);
        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function filasSalida(array $movimientos): array
    {
        return array_map(fn (object $movimiento): array => [
            $movimiento->MovimientoTesoreriaID,
            $movimiento->Tipo,
            $movimiento->GastoID ?? '-',
            (string) $movimiento->FechaContable,
            self::FECHA_CORRECTA,
            'S/ '.number_format((float) $movimiento->Monto, 2, '.', ','),
        ], $movimientos);
    }
}
