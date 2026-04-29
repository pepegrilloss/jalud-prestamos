<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
<<<<<<< HEAD
=======
use App\Filament\Widgets\GenerarCreditoTotalPorGenerarStats;
use App\Filament\Widgets\GenerarCreditoCantidadCreditosStats;
>>>>>>> opencode/calm-garden
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
<<<<<<< HEAD
            \App\Filament\Widgets\GenerarCreditosTotalWidget::class,
            \App\Filament\Widgets\GenerarCreditosCantidadWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 2,
=======
            GenerarCreditoTotalPorGenerarStats::class,
            GenerarCreditoCantidadCreditosStats::class,
>>>>>>> opencode/calm-garden
        ];
    }
}