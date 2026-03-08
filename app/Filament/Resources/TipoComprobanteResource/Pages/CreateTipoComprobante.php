<?php

namespace App\Filament\Resources\TipoComprobanteResource\Pages;

use App\Filament\Resources\TipoComprobanteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoComprobante extends CreateRecord
{
    protected static string $resource = TipoComprobanteResource::class;

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
        return 'Tipo de Comprobante creado correctamente';
    }
}
