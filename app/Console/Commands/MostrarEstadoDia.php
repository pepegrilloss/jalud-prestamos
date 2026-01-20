<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AperturaCierreDia;

class MostrarEstadoDia extends Command
{
    protected $signature = 'dia:estado';
    protected $description = 'Muestra el estado actual del día de operaciones';

    public function handle()
    {
        $estado = AperturaCierreDia::estadoDiaActual();
        $registro = AperturaCierreDia::hoyOHoy();
        $abierto = AperturaCierreDia::estaAbierto();

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('           ESTADO DEL DÍA DE OPERACIONES');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('');

        if ($abierto) {
            $this->line("<fg=green;options=bold>✅ DÍA ABIERTO</>");
            $this->line("<fg=green>   Las operaciones están permitidas</>");
        } else {
            $this->line("<fg=red;options=bold>❌ DÍA CERRADO</>");
            $this->line("<fg=red>   Las operaciones están BLOQUEADAS</>");
        }

        $this->info('');
        $this->info('───────────────────────────────────────────────────────────');

        if ($registro) {
            $this->info("Fecha: <fg=cyan>" . $registro->Fecha->format('d/m/Y') . "</>");
            
            if ($registro->FechaApertura) {
                $this->info("Apertura: <fg=cyan>" . $registro->FechaApertura->format('d/m/Y H:i:s') . "</>");
                if ($registro->usuarioApertura) {
                    $this->info("  Por: " . $registro->usuarioApertura->name);
                }
            }

            if ($registro->FechaCierre) {
                $this->info("Cierre: <fg=cyan>" . $registro->FechaCierre->format('d/m/Y H:i:s') . "</>");
                if ($registro->usuarioCierre) {
                    $this->info("  Por: " . $registro->usuarioCierre->name);
                }
            }

            if ($registro->Observaciones) {
                $this->info("Observaciones: " . $registro->Observaciones);
            }
        } else {
            $this->warning('Sin registro para hoy');
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('');

        return Command::SUCCESS;
    }
}
