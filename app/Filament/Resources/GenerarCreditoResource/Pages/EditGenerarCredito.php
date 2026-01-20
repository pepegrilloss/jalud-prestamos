<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use App\Traits\BloquearPorDiaCerrado;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGenerarCredito extends EditRecord
{
    use BloquearPorDiaCerrado;

    protected static string $resource = GenerarCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
