<?php

namespace App\Filament\Resources\TipoCreditoResource\Pages;

use App\Filament\Resources\TipoCreditoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoCredito extends CreateRecord
{
    protected static string $resource = TipoCreditoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
