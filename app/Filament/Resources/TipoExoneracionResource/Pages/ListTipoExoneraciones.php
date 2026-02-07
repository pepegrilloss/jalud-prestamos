<?php

namespace App\Filament\Resources\TipoExoneracionResource\Pages;

use App\Filament\Resources\TipoExoneracionResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListTipoExoneraciones extends ListRecords
{
    protected static string $resource = TipoExoneracionResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos de Exoneración';
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
