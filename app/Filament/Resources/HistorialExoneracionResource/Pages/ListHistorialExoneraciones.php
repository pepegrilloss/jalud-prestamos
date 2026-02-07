<?php

namespace App\Filament\Resources\HistorialExoneracionResource\Pages;

use App\Filament\Resources\HistorialExoneracionResource;
use Filament\Resources\Pages\ListRecords;

class ListHistorialExoneraciones extends ListRecords
{
    protected static string $resource = HistorialExoneracionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
