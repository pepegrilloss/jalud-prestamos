<?php

namespace App\Filament\Resources\ZonaResource\Pages;

use App\Filament\Resources\ZonaResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZonas extends ListRecords
{
    protected static string $resource = ZonaResource::class;

    public function getTitle(): string
    {
        $title = 'Zonas';
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
