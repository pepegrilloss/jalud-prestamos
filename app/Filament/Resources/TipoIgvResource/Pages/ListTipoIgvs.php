<?php

namespace App\Filament\Resources\TipoIgvResource\Pages;

use App\Filament\Resources\TipoIgvResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ListTipoIgvs extends ManageRecords
{
    protected static string $resource = TipoIgvResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth('md')
                ->modalHeading('Nuevo Tipo IGV'),
        ];
    }
}
