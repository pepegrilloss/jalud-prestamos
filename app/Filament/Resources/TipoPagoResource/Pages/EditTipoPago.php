<?php

namespace App\Filament\Resources\TipoPagoResource\Pages;

use App\Filament\Resources\TipoPagoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoPago extends EditRecord
{
    protected static string $resource = TipoPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Tipo de Pago actualizado correctamente';
    }
}
