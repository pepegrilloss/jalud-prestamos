<?php

namespace App\Filament\Resources\NivelAprobacionResource\Pages;

use App\Filament\Resources\NivelAprobacionResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNivelAprobacions extends ListRecords
{
    protected static string $resource = NivelAprobacionResource::class;

    public function getTitle(): string
    {
        $title = 'Niveles de Aprobación';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
