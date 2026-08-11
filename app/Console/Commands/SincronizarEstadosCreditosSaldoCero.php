<?php

namespace App\Console\Commands;

use App\Services\SaldoPendienteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarEstadosCreditosSaldoCero extends Command
{
    protected $signature = 'creditos:sincronizar-estados-saldo-cero
        {--fix : Corrige los registros encontrados}
        {--json : Muestra el resultado como JSON}';

    protected $description = 'Audita créditos activos con saldo cero y, opcionalmente, sincroniza su estado';

    public function handle(): int
    {
        $registros = DB::table('Credito as c')
            ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
            ->where('c.Activo', 1)
            ->where('c.EstatusCreditoFinal', 'ACTIVO')
            ->where('pc.MontoTotalPagar', '>', 0.009)
            ->where('pc.SaldoPendiente', '<=', 0.009)
            ->orderBy('c.CreditoID')
            ->get([
                'c.CreditoID',
                'pc.ProposicionCreditoID',
                'pc.CodigoCredito',
                'pc.SaldoPendiente',
            ]);

        $resultado = [];

        foreach ($registros as $registro) {
            $item = [
                'credito_id' => (int) $registro->CreditoID,
                'codigo' => $registro->CodigoCredito,
                'saldo_antes' => (float) $registro->SaldoPendiente,
                'corregido' => false,
            ];

            if ($this->option('fix')) {
                $saldo = SaldoPendienteService::recalcular((int) $registro->ProposicionCreditoID);
                $estado = DB::table('Credito')
                    ->where('CreditoID', $registro->CreditoID)
                    ->value('EstatusCreditoFinal');

                $item['saldo_despues'] = $saldo;
                $item['estado_despues'] = $estado;
                $item['corregido'] = $saldo <= 0.009 && $estado === 'SALDADO';
            }

            $resultado[] = $item;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => count($resultado),
                'fix' => (bool) $this->option('fix'),
                'registros' => $resultado,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($resultado === []) {
            $this->info('No se encontraron créditos activos con saldo cero.');
            return self::SUCCESS;
        }

        $this->table(
            ['Crédito', 'Saldo', 'Corregido'],
            array_map(fn (array $item): array => [
                $item['codigo'],
                number_format($item['saldo_antes'], 2),
                $item['corregido'] ? 'Sí' : 'No',
            ], $resultado)
        );

        if (! $this->option('fix')) {
            $this->warn('Auditoría solamente. Ejecute nuevamente con --fix para corregir estos registros.');
        }

        return self::SUCCESS;
    }
}
