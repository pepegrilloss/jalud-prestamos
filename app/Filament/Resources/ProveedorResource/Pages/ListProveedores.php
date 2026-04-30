<?php

namespace App\Filament\Resources\ProveedorResource\Pages;

use App\Filament\Resources\ProveedorResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProveedores extends ListRecords
{
    protected static string $resource = ProveedorResource::class;

    public function getTitle(): string
    {
        $title = 'Proveedores';
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
