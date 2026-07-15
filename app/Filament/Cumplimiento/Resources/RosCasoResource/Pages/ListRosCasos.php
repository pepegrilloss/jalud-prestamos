<?php

namespace App\Filament\Cumplimiento\Resources\RosCasoResource\Pages;

use App\Filament\Cumplimiento\Resources\RosCasoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRosCasos extends ListRecords
{
    protected static string $resource = RosCasoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
