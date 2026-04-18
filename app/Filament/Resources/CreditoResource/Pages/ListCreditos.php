<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListCreditos extends ListRecords
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Créditos Generados';

    public ?string $fechaFiltro = null;

    public function mount(): void
    {
        parent::mount();
        $defaultDate = now()->toDateString();
        $hasDataToday = \App\Models\Credito::whereDate('FechaGeneracion', $defaultDate)->exists();
        
        $this->fechaFiltro = session()->get('creditos_fecha_filtro_v2', request()->query('fechaSeleccionada', $hasDataToday ? $defaultDate : null));
    }

    #[\Livewire\Attributes\On('updateFechaCreditos')]
    public function updateFechaFiltro($fecha)
    {
        $this->fechaFiltro = $fecha;
    }

    public function getTitle(): string
    {
        $title = 'Créditos Generados';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\CreditoResource\Widgets\CreditosDelDiaStats::class,
        ];
    }
    protected function getHeaderActions(): array
    {
        return [];
    }
}
