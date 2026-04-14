<?php

namespace App\Filament\Resources\ResolucionExcedenteResource\Pages;

use App\Filament\Resources\ResolucionExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResolucionExcedentes extends ListRecords
{
    protected static string $resource = ResolucionExcedenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
