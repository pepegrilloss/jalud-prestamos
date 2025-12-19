<?php

namespace App\Filament\Resources\AprobacionProposicionResource\Pages;

use App\Filament\Resources\AprobacionProposicionResource;
use Filament\Resources\Pages\ListRecords;

class ListAprobacionProposicions extends ListRecords
{
    protected static string $resource = AprobacionProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sin botón de crear, las aprobaciones se crean automáticamente
        ];
    }
}
