<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGenerarCredito extends ViewRecord
{
    protected static string $resource = GenerarCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
