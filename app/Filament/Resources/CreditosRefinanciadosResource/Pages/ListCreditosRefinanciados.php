<?php

namespace App\Filament\Resources\CreditosRefinanciadosResource\Pages;

use App\Filament\Resources\CreditosRefinanciadosResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditosRefinanciados extends ListRecords
{
    protected static string $resource = CreditosRefinanciadosResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
