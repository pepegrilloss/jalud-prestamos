<?php

namespace App\Filament\Resources\TipoExoneracionResource\Pages;

use App\Filament\Resources\TipoExoneracionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoExoneracion extends CreateRecord
{
    protected static string $resource = TipoExoneracionResource::class;

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
        return 'Tipo de Exoneración creado correctamente';
    }
}
