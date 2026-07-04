<?php

namespace App\Filament\Resources\SaldarCreditoResource\Pages;

use App\Filament\Resources\SaldarCreditoResource;
use Filament\Resources\Pages\ListRecords;

class ListSaldarCreditos extends ListRecords
{
    protected static string $resource = SaldarCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
