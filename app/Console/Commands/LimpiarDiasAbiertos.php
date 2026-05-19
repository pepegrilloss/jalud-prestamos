<?php

namespace App\Console\Commands;

use App\Models\AperturaCierreDia;
use Illuminate\Console\Command;

class LimpiarDiasAbiertos extends Command
{
    protected $signature = 'apertura:limpiar';
    protected $description = 'Cierra los días abiertos duplicados por cada sede';

    public function handle()
    {
        // Este comando opera sobre TODAS las sedes. Agrupa por SedeID y conserva un día abierto por sede.
        $this->info('🔍 Buscando días abiertos...');
        
        $diasAbiertos = AperturaCierreDia::where('EstadoDia', 'ABIERTO')
            ->orderBy('Fecha', 'desc')
            ->get();

        if ($diasAbiertos->count() === 0) {
            $this->info('✅ No hay días abiertos.');
            return;
        }

        // Agrupar por sede para no mezclar días entre sedes
        $grupos = $diasAbiertos->groupBy('SedeID');

        $totalCerrados = 0;

        foreach ($grupos as $sedeId => $grupo) {
            $sedeNombre = \App\Models\Sede::withoutGlobalScopes()->find($sedeId)?->Nombre ?? "Sede #{$sedeId}";

            if ($grupo->count() === 1) {
                $this->line("✅ {$sedeNombre}: 1 día abierto. OK.");
                continue;
            }

            $this->warn("⚠️ {$sedeNombre}: {$grupo->count()} días abiertos. Conservando el más reciente.");

            $this->table(
                ['ID', 'Fecha', 'Estado', 'Sede'],
                $grupo->map(fn($d) => [
                    'id' => $d->AperturaCierreDiaID,
                    'fecha' => $d->Fecha->format('d/m/Y'),
                    'estado' => $d->EstadoDia,
                    'sede' => $sedeNombre,
                ])->toArray()
            );

            // Conservar el más reciente (primero por orden), cerrar el resto
            $diasAClosar = $grupo->skip(1);

            foreach ($diasAClosar as $dia) {
                $dia->update([
                    'EstadoDia' => 'CERRADO',
                    'FechaCierre' => now(),
                ]);
                $this->line("  ✓ Cerrado: {$dia->Fecha->format('d/m/Y')} (ID: {$dia->AperturaCierreDiaID})");
                $totalCerrados++;
            }
            $this->line('');
        }

        if ($totalCerrados > 0) {
            $this->info("✅ {$totalCerrados} día(s) cerrado(s).");
        }
    }
}
