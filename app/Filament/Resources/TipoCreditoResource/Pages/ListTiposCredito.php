<?php

namespace App\Filament\Resources\TipoCreditoResource\Pages;

use App\Filament\Resources\TipoCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiposCredito extends ListRecords
{
    protected static string $resource = TipoCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
