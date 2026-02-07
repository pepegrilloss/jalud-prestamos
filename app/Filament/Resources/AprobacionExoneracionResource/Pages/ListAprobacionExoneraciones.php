<?php

namespace App\Filament\Resources\AprobacionExoneracionResource\Pages;

use App\Filament\Resources\AprobacionExoneracionResource;
use Filament\Resources\Pages\ListRecords;

class ListAprobacionExoneraciones extends ListRecords
{
    protected static string $resource = AprobacionExoneracionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
