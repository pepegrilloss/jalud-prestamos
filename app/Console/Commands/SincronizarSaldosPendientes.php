<?php

namespace App\Console\Commands;

use App\Services\SaldoPendienteService;
use Illuminate\Console\Command;

class SincronizarSaldosPendientes extends Command
{
    protected $signature = 'saldos:sincronizar';
    protected $description = 'Recalcula y sincroniza el SaldoPendiente de todas las proposiciones activas';

    public function handle(): int
    {
        $this->info('Iniciando sincronización de saldos pendientes...');

        $bar = $this->output->createProgressBar(
            \Illuminate\Support\Facades\DB::table('ProposicionCredito')->where('Activo', true)->count()
        );
        $bar->start();

        $proposiciones = \Illuminate\Support\Facades\DB::table('ProposicionCredito')
            ->where('Activo', true)
            ->pluck('ProposicionCreditoID');

        $actualizados = 0;
        foreach ($proposiciones as $id) {
            SaldoPendienteService::recalcular($id);
            $actualizados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$actualizados} saldos sincronizados correctamente.");

        return Command::SUCCESS;
    }
}
