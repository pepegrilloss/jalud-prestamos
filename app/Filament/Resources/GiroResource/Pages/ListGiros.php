<?php

namespace App\Filament\Resources\GiroResource\Pages;

use App\Filament\Resources\GiroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGiros extends ListRecords
{
    protected static string $resource = GiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
