<?php

namespace App\Console\Commands;

use App\Jobs\CalcularMoraAutomatica;
use Illuminate\Console\Command;

class CalcularMoraCommand extends Command
{
    protected $signature = 'mora:calcular {--date= : Fecha específica formato Y-m-d, sin parámetro calcula para hoy}';

    protected $description = 'Calcula la mora automática para créditos vencidos';

    public function handle(): int
    {
        $this->info('Iniciando cálculo de mora automática...');

        try {
            CalcularMoraAutomatica::dispatch($this->option('date'));
            $this->info('✅ Cálculo de mora encolado correctamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error al calcular mora: ' . $e->getMessage());
            return 1;
        }
    }
}
