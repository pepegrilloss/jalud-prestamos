<?php

namespace App\Filament\Resources\TasaResource\Pages;

use App\Filament\Resources\TasaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasas extends ListRecords
{
    protected static string $resource = TasaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
