<?php

namespace App\Filament\Resources\MotivoResource\Pages;

use App\Filament\Resources\MotivoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListMotivos extends ListRecords
{
    protected static string $resource = MotivoResource::class;

    public function getTitle(): string
    {
        $title = 'Motivos de Gasto';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
