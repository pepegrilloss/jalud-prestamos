<?php

namespace App\Filament\Resources\TasaMoraResource\Pages;

use App\Filament\Resources\TasaMoraResource;
use Filament\Resources\Pages\ListRecords;

class ListTasaMoras extends ListRecords
{
    protected static string $resource = TasaMoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
