<?php

namespace App\Console\Commands;

use App\Jobs\CalcularMoraAutomatica;
use App\Models\AperturaCierreDia;
use App\Models\Sede;
use Illuminate\Console\Command;

class CalcularMoraCommand extends Command
{
    protected $signature = 'mora:calcular
        {--date= : Fecha específica formato Y-m-d}
        {--sede-id= : Sede operativa que se desea procesar}';

    protected $description = 'Calcula la mora automática para créditos vencidos';

    public function handle(): int
    {
        $this->info('Iniciando cálculo de mora automática...');

        try {
            $fecha = $this->option('date');
            $sedeId = $this->option('sede-id');

            if ($fecha && ! $sedeId) {
                $this->error('Para calcular una fecha específica debe indicar también --sede-id.');

                return self::FAILURE;
            }

            if ($sedeId) {
                $sede = Sede::find((int) $sedeId);

                if (! $sede || ! $sede->Activo || str_contains(mb_strtolower($sede->Nombre), 'gerencia')) {
                    $this->error('La sede indicada no existe, está inactiva o corresponde a Gerencia.');

                    return self::FAILURE;
                }

                $fecha ??= AperturaCierreDia::withoutGlobalScope('sede')
                    ->where('SedeID', $sede->SedeID)
                    ->where('EstadoDia', 'ABIERTO')
                    ->latest('Fecha')
                    ->value('Fecha');

                if (! $fecha) {
                    $this->error("{$sede->Nombre} no tiene una fecha abierta.");

                    return self::FAILURE;
                }

                CalcularMoraAutomatica::dispatch($fecha, (int) $sede->SedeID);
                $this->info("Cálculo encolado para {$sede->Nombre} - {$fecha}.");

                return self::SUCCESS;
            }

            $aperturas = AperturaCierreDia::withoutGlobalScope('sede')
                ->where('EstadoDia', 'ABIERTO')
                ->whereHas('sede', fn ($query) => $query
                    ->where('Activo', true)
                    ->whereRaw('LOWER(Nombre) NOT LIKE ?', ['%gerencia%']))
                ->with('sede:SedeID,Nombre')
                ->get()
                ->sortByDesc('Fecha')
                ->unique('SedeID');

            if ($aperturas->isEmpty()) {
                $this->warn('No hay sedes operativas con fecha abierta.');

                return self::SUCCESS;
            }

            foreach ($aperturas as $apertura) {
                $fechaSede = $apertura->Fecha->toDateString();
                CalcularMoraAutomatica::dispatch($fechaSede, (int) $apertura->SedeID);
                $this->line("- {$apertura->sede->Nombre}: {$fechaSede}");
            }

            $this->info('Cálculos encolados según la fecha abierta de cada sede.');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error al calcular mora: ' . $e->getMessage());
            return 1;
        }
    }
}
