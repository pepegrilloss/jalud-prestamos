<?php

namespace App\Filament\Resources\AprobacionResolucionResource\Pages;

use App\Filament\Resources\AprobacionResolucionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAprobacionResoluciones extends ListRecords
{
    protected static string $resource = AprobacionResolucionResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
