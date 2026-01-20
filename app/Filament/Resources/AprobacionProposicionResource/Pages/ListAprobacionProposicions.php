<?php

namespace App\Filament\Resources\AprobacionProposicionResource\Pages;

use App\Filament\Resources\AprobacionProposicionResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListAprobacionProposicions extends ListRecords
{
    protected static string $resource = AprobacionProposicionResource::class;

    public function getTitle(): string
    {
        $title = 'Aprobaciones De Proposiciones';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Sin botón de crear, las aprobaciones se crean automáticamente
        ];
    }
}
