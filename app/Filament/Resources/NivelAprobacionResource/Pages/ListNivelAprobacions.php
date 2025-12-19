<?php

namespace App\Filament\Resources\NivelAprobacionResource\Pages;

use App\Filament\Resources\NivelAprobacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNivelAprobacions extends ListRecords
{
    protected static string $resource = NivelAprobacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
