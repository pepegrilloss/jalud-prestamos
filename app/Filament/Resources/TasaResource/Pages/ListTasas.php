<?php

namespace App\Filament\Resources\TasaResource\Pages;

use App\Filament\Resources\TasaResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasas extends ListRecords
{
    protected static string $resource = TasaResource::class;

    public function getTitle(): string
    {
        $title = 'Tasas';
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
