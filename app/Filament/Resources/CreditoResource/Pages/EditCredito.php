<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Traits\BloquearPorDiaCerrado;
use Filament\Resources\Pages\EditRecord;

class EditCredito extends EditRecord
{
    use BloquearPorDiaCerrado;

    protected static string $resource = CreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
