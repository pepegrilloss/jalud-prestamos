<?php

namespace App\Filament\Resources\MoraResource\Pages;

use App\Filament\Resources\MoraResource;
use Filament\Resources\Pages\ListRecords;

class ListMoras extends ListRecords
{
    protected static string $resource = MoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Solo lectura, sin acciones de crear/editar
        ];
    }
}
