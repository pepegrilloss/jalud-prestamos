<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use Filament\Resources\Pages\EditRecord;

class EditCredito extends EditRecord
{
    protected static string $resource = CreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
