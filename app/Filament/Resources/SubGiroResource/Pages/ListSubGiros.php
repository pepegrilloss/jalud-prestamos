<?php

namespace App\Filament\Resources\SubGiroResource\Pages;

use App\Filament\Resources\SubGiroResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubGiros extends ListRecords
{
    protected static string $resource = SubGiroResource::class;

    public function getTitle(): string
    {
        $title = 'Sub Giros';
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
