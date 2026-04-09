<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListCreditos extends ListRecords
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Créditos Generados';

    public function getTitle(): string
    {
        $title = 'Créditos Generados';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    public function updatedTableFilters(): void
    {
        // Enviar evento seguro al widget en lugar de usar InteractsWithPageTable
        $this->dispatch('update-fecha-stats', 
            fecha: $this->tableFilters['fecha_filtro']['fecha'] ?? now()->toDateString()
        );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CreditoResource\Widgets\CreditosDelDiaStats::class,
        ];
    }
    protected function getHeaderActions(): array
    {
        return [];
    }
}
