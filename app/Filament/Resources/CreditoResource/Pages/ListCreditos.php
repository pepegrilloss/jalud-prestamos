<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditos extends ListRecords
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Créditos Generados';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
