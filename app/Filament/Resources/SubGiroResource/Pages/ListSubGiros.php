<?php

namespace App\Filament\Resources\SubGiroResource\Pages;

use App\Filament\Resources\SubGiroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubGiros extends ListRecords
{
    protected static string $resource = SubGiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
