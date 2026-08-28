<?php

namespace App\Console\Commands;

use App\Models\AperturaCierreDia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CorregirMorasFueraFechaSede extends Command
{
    protected $signature = 'moras:corregir-fuera-fecha-sede
        {--fix : Respalda y elimina las moras posteriores a la fecha abierta de cada sede}
        {--sede-id= : Audita únicamente una sede}';

    protected $description = 'Audita moras creadas después de la fecha operativa abierta de cada sede';

    public function handle(): int
    {
        $aperturas = AperturaCierreDia::withoutGlobalScope('sede')
            ->where('EstadoDia', 'ABIERTO')
            ->when($this->option('sede-id'), fn ($query, $sedeId) => $query->where('SedeID', (int) $sedeId))
            ->whereHas('sede', fn ($query) => $query
                ->where('Activo', true)
                ->whereRaw('LOWER(Nombre) NOT LIKE ?', ['%gerencia%']))
            ->with('sede:SedeID,Nombre')
            ->get()
            ->sortByDesc('Fecha')
            ->unique('SedeID');

        if ($aperturas->isEmpty()) {
            $this->warn('No hay sedes operativas con fecha abierta para auditar.');

            return self::SUCCESS;
        }

        $filas = [];
        $morasAEliminar = collect();

        foreach ($aperturas as $apertura) {
            $fechaAbierta = $apertura->Fecha->toDateString();
            $moras = DB::table('mora')
                ->where('SedeID', $apertura->SedeID)
                ->whereDate('FechaMora', '>', $fechaAbierta)
                ->orderBy('MoraID')
                ->get();

            $filas[] = [
                $apertura->sede->Nombre,
                $fechaAbierta,
                $moras->count(),
                'S/ '.number_format((float) $moras->sum('MontoMora'), 2),
            ];

            $morasAEliminar = $morasAEliminar->concat($moras);
        }

        $this->table(['Sede', 'Fecha abierta', 'Moras posteriores', 'Monto'], $filas);

        if ($morasAEliminar->isEmpty()) {
            $this->info('No se encontraron inconsistencias.');

            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->warn('Modo auditoría: no se modificó ningún registro. Use --fix para corregir.');

            return self::SUCCESS;
        }

        $ruta = 'backups/moras/moras_fuera_fecha_sede_'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($ruta, $morasAEliminar->values()->toJson(JSON_PRETTY_PRINT));

        $ids = $morasAEliminar->pluck('MoraID')->map(fn ($id) => (int) $id);
        $eliminadas = DB::transaction(function () use ($ids): int {
            $total = 0;

            foreach ($ids->chunk(500) as $chunk) {
                $total += DB::table('mora')->whereIn('MoraID', $chunk->all())->delete();
            }

            return $total;
        });

        $this->info("Se eliminaron {$eliminadas} moras fuera de la fecha operativa.");
        $this->line('Respaldo: '.Storage::disk('local')->path($ruta));

        return self::SUCCESS;
    }
}
