<?php

namespace App\Filament\Resources\TipoPagoResource\Pages;

use App\Filament\Resources\TipoPagoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoPago extends CreateRecord
{
    protected static string $resource = TipoPagoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tipo de Pago creado correctamente';
    }
}
