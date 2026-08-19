<?php

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegularizarPagosMayorSicopa extends Command
{
    protected $signature = 'pagos:regularizar-a-mayor-sicopa
                            {--fix : Aplica la regularizacion validada}
                            {--usuario-id= : Usuario responsable de la regularizacion}';

    protected $description = 'Regulariza como pagos a mayor los registros identificados de la migracion SICOPA';

    private const PAGOS = [
        108860 => ['codigo' => 'C-004648', 'cliente' => 'ALIAGA QUIJANO SARA EUDOCIA', 'fecha' => '2026-06-26', 'monto' => 124.00],
        101171 => ['codigo' => 'C-002106', 'cliente' => 'TORRES CHAVEZ BERTHA EUSEBIA', 'fecha' => '2026-06-08', 'monto' => 100.00],
        105326 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-06-18', 'monto' => 12.00],
        106296 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-06-20', 'monto' => 12.00],
        107210 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-06-23', 'monto' => 12.00],
        108157 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-06-25', 'monto' => 12.00],
        100905 => ['codigo' => 'C-004645', 'cliente' => 'NUÑEZ CARRANZA JOSE', 'fecha' => '2026-06-06', 'monto' => 210.00],
    ];

    private const PAGOS_YA_CLASIFICADOS = [
        110037 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-06-30', 'monto' => 12.00],
        111366 => ['codigo' => 'C-005646', 'cliente' => 'OLIVERA TORRES SARA', 'fecha' => '2026-07-03', 'monto' => 15.00],
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('fix');
        $usuarioId = $this->option('usuario-id');

        if ($aplicar && (! is_numeric($usuarioId) || (int) $usuarioId <= 0)) {
            $this->error('Para aplicar debe indicar --usuario-id con el usuario responsable.');

            return self::FAILURE;
        }

        if ($aplicar && ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            $this->error("No existe el usuario {$usuarioId}.");

            return self::FAILURE;
        }

        try {
            $resultado = DB::transaction(function () use ($aplicar, $usuarioId): array {
                $todos = self::PAGOS + self::PAGOS_YA_CLASIFICADOS;
                $registros = $this->consultarPagos(array_keys($todos), $aplicar);

                if ($registros->count() !== count($todos)) {
                    $faltantes = array_diff(array_keys($todos), $registros->keys()->all());
                    throw new RuntimeException('Faltan pagos esperados: '.implode(', ', $faltantes).'.');
                }

                foreach ($todos as $pagoId => $esperado) {
                    $this->validarPago($registros->get($pagoId), $esperado);
                }

                foreach (self::PAGOS_YA_CLASIFICADOS as $pagoId => $esperado) {
                    $pago = $registros->get($pagoId);
                    if (! (bool) $pago->EsPagoAMayor || (bool) $pago->EsPagoAMayorPorMora) {
                        throw new RuntimeException("El PagoID {$pagoId} debía estar clasificado previamente como pago a mayor.");
                    }
                }

                $pendientes = collect(array_keys(self::PAGOS))
                    ->filter(fn (int $pagoId): bool => ! (bool) $registros->get($pagoId)->EsPagoAMayor)
                    ->values();

                if ($pendientes->isNotEmpty()) {
                    $vinculados = DB::table('solicitudes_resolucion_excedente')
                        ->whereIn('PagoOrigenID', $pendientes)
                        ->pluck('PagoOrigenID')
                        ->unique()
                        ->values();

                    if ($vinculados->isNotEmpty()) {
                        throw new RuntimeException('Hay pagos aún no clasificados que ya poseen solicitudes: '.$vinculados->implode(', ').'.');
                    }
                }

                if (! $aplicar || $pendientes->isEmpty()) {
                    return ['aplicados' => [], 'registros' => $registros];
                }

                $ahora = now();
                foreach ($pendientes as $pagoId) {
                    $pago = $registros->get($pagoId);
                    $oldValues = [
                        'EsPagoAMayor' => (bool) $pago->EsPagoAMayor,
                        'EsPagoAMayorPorMora' => (bool) $pago->EsPagoAMayorPorMora,
                        'UserModificacionID' => $pago->UserModificacionID,
                        'FechaModificacion' => $pago->FechaModificacion,
                    ];
                    $newValues = [
                        'EsPagoAMayor' => true,
                        'EsPagoAMayorPorMora' => false,
                        'UserModificacionID' => (int) $usuarioId,
                        'FechaModificacion' => $ahora->toDateTimeString(),
                    ];

                    DB::table('pago')->where('PagoID', $pagoId)->update($newValues);

                    Log::registrar(
                        'REG_AM_SICOPA',
                        'Pago',
                        $pagoId,
                        $oldValues,
                        $newValues + [
                            'CodigoCredito' => $pago->CodigoCredito,
                            'Motivo' => 'Regularizacion de pago a mayor proveniente de migracion SICOPA',
                        ],
                        (int) $pago->SedeID,
                        (int) $usuarioId
                    );
                }

                return ['aplicados' => $pendientes->all(), 'registros' => $registros];
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('No se modificó ningún registro.');

            return self::FAILURE;
        }

        $this->mostrarResumen($resultado['registros']);

        if (! $aplicar) {
            $this->warn('Modo auditoría: no se modificó ningún pago. Revise el resultado y ejecute con --fix --usuario-id=ID.');

            return self::SUCCESS;
        }

        if ($resultado['aplicados'] === []) {
            $this->info('Todos los pagos ya estaban regularizados. No se realizó ningún cambio.');

            return self::SUCCESS;
        }

        $this->info('Regularización aplicada y auditada. PagoID modificados: '.implode(', ', $resultado['aplicados']).'.');
        $this->line('No se modificaron montos, fechas de pago, créditos, saldos ni movimientos de caja.');

        return self::SUCCESS;
    }

    private function consultarPagos(array $pagoIds, bool $bloquear)
    {
        $query = DB::table('pago as p')
            ->join('Credito as c', 'p.CreditoID', '=', 'c.CreditoID')
            ->join('ProposicionCredito as pc', 'c.ProposicionCreditoID', '=', 'pc.ProposicionCreditoID')
            ->join('Cliente as cl', 'pc.ClienteID', '=', 'cl.ClienteID')
            ->whereIn('p.PagoID', $pagoIds)
            ->select([
                'p.PagoID',
                'p.FechaPago',
                'p.MontoPagado',
                'p.EsPagoAMayor',
                'p.EsPagoAMayorPorMora',
                'p.Activo',
                'p.SedeID',
                'p.UserModificacionID',
                'p.FechaModificacion',
                'pc.CodigoCredito',
                'cl.NombresApellidos',
            ]);

        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('PagoID');
    }

    private function validarPago(object $pago, array $esperado): void
    {
        $fecha = substr((string) $pago->FechaPago, 0, 10);
        $cliente = mb_strtoupper(trim((string) $pago->NombresApellidos), 'UTF-8');

        if (
            $pago->CodigoCredito !== $esperado['codigo']
            || $cliente !== $esperado['cliente']
            || $fecha !== $esperado['fecha']
            || abs((float) $pago->MontoPagado - $esperado['monto']) > 0.001
            || ! (bool) $pago->Activo
            || (bool) $pago->EsPagoAMayorPorMora
        ) {
            throw new RuntimeException("El PagoID {$pago->PagoID} no coincide con los datos validados de SICOPA.");
        }
    }

    private function mostrarResumen($registros): void
    {
        $filas = [];
        foreach (self::PAGOS as $pagoId => $esperado) {
            $pago = $registros->get($pagoId);
            $filas[] = [
                $pagoId,
                $esperado['codigo'],
                $esperado['fecha'],
                'S/ '.number_format($esperado['monto'], 2),
                (bool) $pago->EsPagoAMayor ? 'YA REGULARIZADO' : 'PENDIENTE',
            ];
        }

        $this->table(['PagoID', 'Crédito', 'Fecha', 'Monto', 'Estado'], $filas);
        $this->line('C-005646: S/ 48.00 pendientes + S/ 27.00 ya clasificados = S/ 75.00.');
        $this->line('Total inicialmente pendiente de regularización: S/ 482.00.');
    }
}
