<?php

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CorregirFechaRemesaGerenciaOctubre2026 extends Command
{
    protected $signature = 'gerencia:corregir-fecha-remesa-octubre-2026
                            {--movimiento= : ID del movimiento de tesoreria}
                            {--usuario-id= : Usuario responsable, obligatorio para aplicar}
                            {--aplicar : Actualiza la fecha contable validada}
                            {--confirmar= : Confirmacion literal APLICAR-REMESA-GERENCIA-2026-10-02}';

    protected $description = 'Previsualiza y corrige la fecha contable de la remesa de Gerencia del 02/10/2026';

    private const FECHA_ORIGINAL = '2026-10-12';

    private const FECHA_OBJETIVO = '2026-10-02';

    private const CONFIRMACION = 'APLICAR-REMESA-GERENCIA-2026-10-02';

    public function handle(): int
    {
        try {
            $id = $this->option('movimiento');
            if (! ctype_digit((string) $id) || (int) $id <= 0) {
                throw new RuntimeException('Indique el ID del movimiento con --movimiento=ID.');
            }

            $usuarioId = $this->option('aplicar') ? $this->validarAplicacion() : null;
            $resultado = DB::transaction(function () use ($id, $usuarioId): array {
                $query = DB::table('tesoreria_movimientos')->where('MovimientoTesoreriaID', (int) $id);
                if ($usuarioId) {
                    $query->lockForUpdate();
                }
                $movimiento = $query->first();

                if (! $movimiento
                    || $movimiento->Tipo !== 'TRANSFERENCIA'
                    || $movimiento->OrigenTipo !== 'CAJA_GERENCIA'
                    || $movimiento->DestinoTipo !== 'CUENTA_BANCARIA'
                    || round((float) $movimiento->Monto, 2) !== 9019.00
                    || ! str_contains(mb_strtoupper($movimiento->CuentaOrigenNombre), 'GERENCIA')
                    || ! str_contains(mb_strtoupper($movimiento->CuentaDestinoNombre), 'BCP')
                    || ! str_contains(mb_strtoupper($movimiento->CuentaDestinoNombre), 'CHICLAYO')
                    || ! str_contains(mb_strtoupper($movimiento->Concepto), 'CHICLAYO')
                    || substr((string) $movimiento->FechaMovimiento, 0, 19) !== '2026-10-12 16:15:18'
                    || ! in_array((string) $movimiento->FechaContable, [self::FECHA_ORIGINAL, self::FECHA_OBJETIVO], true)) {
                    throw new RuntimeException('El movimiento no coincide con la remesa de S/ 9,019.00 identificada en Gerencia. No se aplicaron cambios.');
                }

                $fechaAnterior = (string) $movimiento->FechaContable;
                $actualizado = 0;
                if ($usuarioId && $fechaAnterior !== self::FECHA_OBJETIVO) {
                    $actualizado = DB::table('tesoreria_movimientos')
                        ->where('MovimientoTesoreriaID', (int) $id)
                        ->where('FechaContable', $fechaAnterior)
                        ->update(['FechaContable' => self::FECHA_OBJETIVO]);
                    if ($actualizado !== 1) {
                        throw new RuntimeException('El movimiento cambió durante la corrección. No se aplicaron cambios.');
                    }

                    Log::registrar(
                        'ACTUALIZAR', 'MovimientoTesoreria', (int) $id,
                        ['FechaContable' => $fechaAnterior],
                        ['FechaContable' => self::FECHA_OBJETIVO],
                        null, $usuarioId,
                    );
                }

                return [$movimiento, $fechaAnterior, $actualizado];
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        [$movimiento, $fechaAnterior, $actualizado] = $resultado;
        $this->table(['Movimiento', 'Tipo', 'Monto', 'Origen', 'Destino', 'Fecha actual', 'Fecha objetivo'], [[
            $movimiento->MovimientoTesoreriaID,
            $movimiento->Tipo,
            'S/ '.number_format((float) $movimiento->Monto, 2),
            $movimiento->CuentaOrigenNombre,
            $movimiento->CuentaDestinoNombre,
            $fechaAnterior,
            self::FECHA_OBJETIVO,
        ]]);
        $this->line('Solo cambia FechaContable; se conservan el monto, los saldos y FechaMovimiento.');

        if (! $this->option('aplicar')) {
            $this->warn('Vista previa: no se modificó ningún dato.');
            $this->line('Para aplicar, agregue --aplicar --usuario-id=ID --confirmar='.self::CONFIRMACION);
        } else {
            $this->info($actualizado ? 'Fecha contable corregida y auditada.' : 'La fecha contable ya estaba corregida; no se hicieron cambios.');
        }

        return self::SUCCESS;
    }

    private function validarAplicacion(): int
    {
        $usuarioId = $this->option('usuario-id');
        if (! ctype_digit((string) $usuarioId)
            || (int) $usuarioId <= 0
            || ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            throw new RuntimeException('Indique --usuario-id con un usuario existente.');
        }

        if ($this->option('confirmar') !== self::CONFIRMACION) {
            throw new RuntimeException('La confirmacion no coincide. Use --confirmar='.self::CONFIRMACION);
        }

        return (int) $usuarioId;
    }
}
