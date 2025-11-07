<?php

namespace App\Filament\Resources\SubGiroResource\Pages;

use App\Filament\Resources\SubGiroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubGiro extends EditRecord
{
    protected static string $resource = SubGiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
