<?php

namespace App\Console\Commands;

use App\Models\AperturaCierreDia;
use Illuminate\Console\Command;

class LimpiarDiasAbiertos extends Command
{
    protected $signature = 'apertura:limpiar';
    protected $description = 'Cierra todos los días abiertos excepto el primero';

    public function handle()
    {
        // Este comando opera sobre TODAS las sedes sin filtro. Es intencional para operaciones de sistema.
        $this->info('🔍 Buscando días abiertos...');
        
        $diasAbiertos = AperturaCierreDia::where('EstadoDia', 'ABIERTO')
            ->orderBy('AperturaCierreDiaID')
            ->get();

        if ($diasAbiertos->count() === 0) {
            $this->info('✅ No hay días abiertos.');
            return;
        }

        if ($diasAbiertos->count() === 1) {
            $this->info('✅ Solo hay 1 día abierto. Todo bien.');
            return;
        }

        $this->warn("⚠️ Hay {$diasAbiertos->count()} días abiertos. Esto es incorrecto.");
        $this->line('');

        // Mostrar todos los días abiertos
        $this->table(
            ['ID', 'Fecha', 'Estado'],
            $diasAbiertos->map(fn($d) => [
                'id' => $d->AperturaCierreDiaID,
                'fecha' => $d->Fecha->format('d/m/Y'),
                'estado' => $d->EstadoDia,
            ])->toArray()
        );

        $this->line('');
        if (!$this->confirm('¿Deseas cerrar todos excepto el primero?')) {
            $this->info('Operación cancelada.');
            return;
        }

        // Cerrar todos excepto el primero
        $primerDia = $diasAbiertos->first();
        $diasAClosar = $diasAbiertos->skip(1);

        foreach ($diasAClosar as $dia) {
            $dia->update([
                'EstadoDia' => 'CERRADO',
                'FechaCierre' => now(),
            ]);
            $this->line("✓ Cerrado: {$dia->Fecha->format('d/m/Y')}");
        }

        $this->info('');
        $this->info("✅ Operación completada. Día abierto: {$primerDia->Fecha->format('d/m/Y')}");
    }
}
