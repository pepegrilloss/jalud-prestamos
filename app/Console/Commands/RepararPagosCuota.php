<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararPagosCuota extends Command
{
    protected $signature = 'sedes:reparar-pagos-cuota
        {--apply : Aplicar cambios en la base de datos}
        {--recalculate-states : Recalcular estado de cuota anterior y nueva despues de aplicar}
        {--json : Mostrar resultado en JSON}';

    protected $description = 'Detecta y repara pagos cuya CuotaID pertenece a un CreditoID distinto al del pago.';

    public function handle(): int
    {
        $rows = DB::table('pago as p')
            ->join('cuota as cu', 'cu.CuotaID', '=', 'p.CuotaID')
            ->leftJoin('Credito as cr', 'cr.CreditoID', '=', 'p.CreditoID')
            ->whereColumn('p.CreditoID', '<>', 'cu.CreditoID')
            ->select([
                'p.PagoID',
                'p.CreditoID as PagoCreditoID',
                'p.CuotaID as CuotaActualID',
                'p.FechaPago',
                'p.MontoPagado',
                'cu.CreditoID as CuotaActualCreditoID',
                'cu.NumeroCuota as CuotaActualNumero',
                'cu.FechaVencimiento as CuotaActualFecha',
                'cr.SedeID as CreditoSedeID',
            ])
            ->orderBy('p.PagoID')
            ->get();

        $results = [];
        $apply = (bool) $this->option('apply');

        foreach ($rows as $row) {
            $match = $this->findCandidate($row);
            $candidate = $match['cuota'];
            $strategy = $match['strategy'];
            $status = $candidate && $strategy === 'fecha_pago' ? 'candidate_found' : ($candidate ? 'fallback_review' : 'manual_review');

            $result = [
                'PagoID' => $row->PagoID,
                'PagoCreditoID' => $row->PagoCreditoID,
                'CuotaActualID' => $row->CuotaActualID,
                'CuotaActualCreditoID' => $row->CuotaActualCreditoID,
                'FechaPago' => $row->FechaPago,
                'NuevaCuotaID' => $candidate?->CuotaID,
                'NuevaCuotaNumero' => $candidate?->NumeroCuota,
                'NuevaCuotaFecha' => $candidate?->FechaVencimiento,
                'strategy' => $strategy,
                'status' => $status,
                'applied' => false,
            ];

            if ($apply && $candidate && $strategy === 'fecha_pago') {
                DB::transaction(function () use ($row, $candidate, &$result) {
                    DB::table('pago')
                        ->where('PagoID', $row->PagoID)
                        ->update(['CuotaID' => $candidate->CuotaID]);

                    if ($this->option('recalculate-states')) {
                        $this->recalculateCuotaState((int) $row->CuotaActualID);
                        $this->recalculateCuotaState((int) $candidate->CuotaID);
                    }

                    $result['applied'] = true;
                });
            }

            $results[] = $result;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => count($results),
                'apply' => $apply,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif (empty($results)) {
            $this->info('No hay pagos con CuotaID de otro credito.');
        } else {
            $this->warn(($apply ? 'Reparacion aplicada/revisada: ' : 'Dry-run: ') . count($results) . ' pago(s).');
            $this->table([
                'PagoID',
                'PagoCreditoID',
                'CuotaActualID',
                'CuotaActualCreditoID',
                'NuevaCuotaID',
                'status',
                'applied',
            ], $results);

            if (!$apply) {
                $this->line('Para aplicar solo las coincidencias claras: php artisan sedes:reparar-pagos-cuota --apply');
            }
        }

        return self::SUCCESS;
    }

    private function findCandidate(object $row): array
    {
        $fechaPago = $row->FechaPago ? Carbon::parse($row->FechaPago)->toDateString() : null;

        if ($fechaPago) {
            $candidates = DB::table('cuota')
                ->where('CreditoID', $row->PagoCreditoID)
                ->where('Activo', 1)
                ->where('NumeroCuota', '>', 0)
                ->whereDate('FechaVencimiento', $fechaPago)
                ->get();

            if ($candidates->count() === 1) {
                return ['cuota' => $candidates->first(), 'strategy' => 'fecha_pago'];
            }
        }

        if ($row->CuotaActualFecha) {
            $candidates = DB::table('cuota')
                ->where('CreditoID', $row->PagoCreditoID)
                ->where('Activo', 1)
                ->where('NumeroCuota', '>', 0)
                ->whereDate('FechaVencimiento', Carbon::parse($row->CuotaActualFecha)->toDateString())
                ->get();

            if ($candidates->count() === 1) {
                return ['cuota' => $candidates->first(), 'strategy' => 'fecha_cuota_actual'];
            }
        }

        return ['cuota' => null, 'strategy' => null];
    }

    private function recalculateCuotaState(int $cuotaId): void
    {
        $cuota = DB::table('cuota')->where('CuotaID', $cuotaId)->first();
        if (!$cuota || (int) $cuota->NumeroCuota <= 0) {
            return;
        }

        $totalPagado = (float) DB::table('pago')
            ->where('CuotaID', $cuotaId)
            ->where('Activo', 1)
            ->where(function ($query) {
                $query->whereNull('EstadoTraslado')
                    ->orWhere('EstadoTraslado', '<>', 'TRASLADADO');
            })
            ->where(function ($query) {
                $query->whereNull('EsMora')
                    ->orWhere('EsMora', 0);
            })
            ->sum('MontoPagado');

        $montoCuota = (float) $cuota->MontoCuota;
        $estado = $cuota->Estado;

        if ($totalPagado >= $montoCuota && $montoCuota > 0) {
            $estado = 'PAGADA';
        } elseif (Carbon::parse($cuota->FechaVencimiento)->isPast()) {
            $estado = 'MORA';
        } else {
            $estado = 'PENDIENTE';
        }

        DB::table('cuota')
            ->where('CuotaID', $cuotaId)
            ->update(['Estado' => $estado]);
    }
}
