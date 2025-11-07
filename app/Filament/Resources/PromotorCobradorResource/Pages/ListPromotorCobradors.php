<?php

namespace App\Filament\Resources\PromotorCobradorResource\Pages;

use App\Filament\Resources\PromotorCobradorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotorCobradors extends ListRecords
{
    protected static string $resource = PromotorCobradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
