<?php

namespace App\Filament\Resources\TasaResource\Pages;

use App\Filament\Resources\TasaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTasa extends ViewRecord
{
    protected static string $resource = TasaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
