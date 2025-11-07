<?php

namespace App\Filament\Resources\CiudadResource\Pages;

use App\Filament\Resources\CiudadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCiudads extends ListRecords
{
    protected static string $resource = CiudadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
