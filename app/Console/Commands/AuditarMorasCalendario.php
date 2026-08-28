<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Services\MoraCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditarMorasCalendario extends Command
{
    protected $signature = 'mora:auditar-calendario
        {--desde=2026-01-01 : Fecha minima de vencimiento}
        {--hasta= : Fecha final del calculo, por defecto hoy}
        {--credito=* : Uno o mas codigos de credito}
        {--fix : Registra las moras faltantes}
        {--json : Devuelve el resultado como JSON}';

    protected $description = 'Audita y, opcionalmente, completa moras diarias faltantes usando saldos historicos';

    public function handle(MoraCalculationService $moraService): int
    {
        $desde = Carbon::parse($this->option('desde'))->startOfDay();
        $hasta = Carbon::parse($this->option('hasta') ?: today())->startOfDay();
        $fix = (bool) $this->option('fix');
        $codigos = array_values(array_filter((array) $this->option('credito')));

        if ($desde->gt($hasta)) {
            $this->error('La fecha --desde no puede ser posterior a --hasta.');

            return self::FAILURE;
        }

        $query = Credito::withoutGlobalScope('sede')
            ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'Credito.ProposicionCreditoID')
            ->where('Credito.Activo', 1)
            ->where('pc.SaldoPendiente', '>', 0)
            ->whereDate('Credito.FechaVencimiento', '>=', $desde)
            ->whereDate('Credito.FechaVencimiento', '<', $hasta)
            ->when($codigos, fn ($q) => $q->whereIn('pc.CodigoCredito', $codigos))
            ->select('Credito.*', 'pc.CodigoCredito')
            ->orderBy('Credito.CreditoID');

        $creditos = $query->get();
        $registros = [];
        $totalMoras = 0;
        $montoTotal = 0.0;

        if ($fix && $creditos->isNotEmpty()) {
            $this->crearRespaldo($creditos->pluck('CreditoID')->all(), $desde, $hasta);
        }

        foreach ($creditos as $credito) {
            if (! $fix) {
                DB::beginTransaction();
            }

            try {
                $resultado = $moraService->procesarCreditoHasta($credito, $hasta, $desde);

                if (! $fix) {
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                if (! $fix && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                throw $e;
            }

            if ($resultado['creadas'] === 0) {
                continue;
            }

            $totalMoras += $resultado['creadas'];
            $montoTotal = round($montoTotal + $resultado['monto'], 2);
            $registros[] = [
                'credito' => $credito->CodigoCredito,
                'moras_faltantes' => $resultado['creadas'],
                'monto' => $resultado['monto'],
            ];
        }

        $salida = [
            'modo' => $fix ? 'FIX' : 'AUDITORIA',
            'creditos_auditados' => $creditos->count(),
            'creditos_afectados' => count($registros),
            'moras_faltantes' => $totalMoras,
            'monto_total' => $montoTotal,
            'registros' => $registros,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Credito', 'Moras faltantes', 'Monto'], $registros);
            $this->info(sprintf(
                '%s: %d creditos afectados, %d moras, S/ %.2f.',
                $salida['modo'],
                $salida['creditos_afectados'],
                $totalMoras,
                $montoTotal,
            ));
        }

        return self::SUCCESS;
    }

    private function crearRespaldo(array $creditoIds, Carbon $desde, Carbon $hasta): void
    {
        $directorio = storage_path('app/backups/moras');
        File::ensureDirectoryExists($directorio);

        $archivo = $directorio.DIRECTORY_SEPARATOR.'moras_antes_fix_'.now()->format('Ymd_His').'.json';
        $datos = DB::table('mora')
            ->whereIn('CreditoID', $creditoIds)
            ->whereDate('FechaMora', '>=', $desde)
            ->whereDate('FechaMora', '<=', $hasta)
            ->orderBy('CreditoID')
            ->orderBy('FechaMora')
            ->get();

        File::put($archivo, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Respaldo creado: '.$archivo);
    }
}
