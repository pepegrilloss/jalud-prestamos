<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAperturaCierreDias extends ListRecords
{
    protected static string $resource = AperturaCierreDiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
