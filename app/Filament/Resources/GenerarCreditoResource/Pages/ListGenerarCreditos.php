<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use App\Filament\Widgets\GenerarCreditoTotalPorGenerarStats;
use App\Filament\Widgets\GenerarCreditoCantidadCreditosStats;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListGenerarCreditos extends ListRecords
{
    protected static string $resource = GenerarCreditoResource::class;

    public function getTitle(): string
    {
        $title = 'Generar Crédito';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GenerarCreditoTotalPorGenerarStats::class,
            GenerarCreditoCantidadCreditosStats::class,
        ];
    }
}