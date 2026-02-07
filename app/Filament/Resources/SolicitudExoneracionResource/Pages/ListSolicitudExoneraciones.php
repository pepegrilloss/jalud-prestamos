<?php

namespace App\Filament\Resources\SolicitudExoneracionResource\Pages;

use App\Filament\Resources\SolicitudExoneracionResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudExoneraciones extends ListRecords
{
    protected static string $resource = SolicitudExoneracionResource::class;

    public function getTitle(): string
    {
        $title = 'Descuentos y Exoneraciones';
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
