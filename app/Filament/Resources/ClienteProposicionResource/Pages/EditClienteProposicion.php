<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use Filament\Resources\Pages\EditRecord;

class EditClienteProposicion extends EditRecord
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
