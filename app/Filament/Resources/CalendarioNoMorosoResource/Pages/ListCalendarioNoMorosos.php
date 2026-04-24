<?php

namespace App\Filament\Resources\CalendarioNoMorosoResource\Pages;

use App\Filament\Resources\CalendarioNoMorosoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalendarioNoMorosos extends ListRecords
{
    protected static string $resource = CalendarioNoMorosoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
