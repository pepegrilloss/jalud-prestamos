<?php

namespace App\Filament\Resources\CiudadResource\Pages;

use App\Filament\Resources\CiudadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCiudad extends ViewRecord
{
    protected static string $resource = CiudadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
