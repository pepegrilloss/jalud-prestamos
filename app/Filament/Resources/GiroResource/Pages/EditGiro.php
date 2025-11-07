<?php

namespace App\Filament\Resources\GiroResource\Pages;

use App\Filament\Resources\GiroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiro extends EditRecord
{
    protected static string $resource = GiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
