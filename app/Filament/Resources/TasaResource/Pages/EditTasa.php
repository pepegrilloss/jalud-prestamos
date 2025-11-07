<?php

namespace App\Filament\Resources\TasaResource\Pages;

use App\Filament\Resources\TasaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTasa extends EditRecord
{
    protected static string $resource = TasaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
