<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use Filament\Resources\Pages\ListRecords;

class ListGenerarCreditos extends ListRecords
{
    protected static string $resource = GenerarCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}