<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;
use Filament\Resources\Pages\ListRecords;

class ListClienteProposicions extends ListRecords
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClienteProposicionStats::class,
        ];
    }
}