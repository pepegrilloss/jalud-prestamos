<?php

namespace App\Console\Commands;

use App\Services\AperturaCierreDiaLogger;
use Illuminate\Console\Command;

class VerAperturaCierreLogs extends Command
{
    protected $signature = 'logs:apertura-cierre {--clear : Limpiar los logs}';
    protected $description = 'Ver logs de apertura y cierre de días';

    public function handle()
    {
        // Este comando opera sobre TODAS las sedes sin filtro. Es intencional para operaciones de sistema.
        $logger = new AperturaCierreDiaLogger();
        
        if ($this->option('clear')) {
            $logger->clearLogs();
            $this->info('✅ Logs limpiados correctamente');
            return;
        }

        $this->info('📋 LOGS DE APERTURA/CIERRE DIA');
        $this->line('═════════════════════════════════════════');
        $this->line('');

        $logs = $logger->getLogs(200);

        if (empty($logs)) {
            $this->warn('No hay logs registrados.');
            return;
        }

        foreach ($logs as $line) {
            if (strpos($line, '[ERROR]') !== false) {
                $this->error($line);
            } elseif (strpos($line, '[WARNING]') !== false) {
                $this->warn($line);
            } elseif (strpos($line, '[SUCCESS]') !== false) {
                $this->info($line);
            } else {
                $this->line($line);
            }
        }

        $this->line('');
        $this->info('═════════════════════════════════════════');
        
        // Mostrar estado actual de la BD
        $this->mostrarEstadoActual();
    }

    protected function mostrarEstadoActual()
    {
        $this->line('');
        $this->info('📊 ESTADO ACTUAL DE LA BASE DE DATOS:');
        $this->line('');
        
        $diasAbiertos = \App\Models\AperturaCierreDia::where('EstadoDia', 'ABIERTO')->get();
        $diasCerrados = \App\Models\AperturaCierreDia::where('EstadoDia', 'CERRADO')->get();
        
        $this->info("Días ABIERTOS: {$diasAbiertos->count()}");
        foreach ($diasAbiertos as $dia) {
            $this->line("  ✓ ID: {$dia->AperturaCierreDiaID} | Fecha: {$dia->Fecha->format('d/m/Y')} | Estado: {$dia->EstadoDia}");
        }
        
        $this->line('');
        $this->info("Días CERRADOS: {$diasCerrados->count()}");
        
        if ($diasCerrados->count() > 0 && $diasCerrados->count() <= 5) {
            foreach ($diasCerrados as $dia) {
                $this->line("  ✓ ID: {$dia->AperturaCierreDiaID} | Fecha: {$dia->Fecha->format('d/m/Y')} | Estado: {$dia->EstadoDia}");
            }
        }
    }
}
