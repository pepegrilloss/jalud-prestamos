<?php

namespace App\Filament\Resources\GiroResource\Pages;

use App\Filament\Resources\GiroResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGiro extends ViewRecord
{
    protected static string $resource = GiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
