<?php

namespace App\Filament\Resources\CiudadResource\Pages;

use App\Filament\Resources\CiudadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCiudad extends EditRecord
{
    protected static string $resource = CiudadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
