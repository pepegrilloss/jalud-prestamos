<?php

namespace App\Filament\Resources\NivelAprobacionResource\Pages;

use App\Filament\Resources\NivelAprobacionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNivelAprobacion extends CreateRecord
{
    protected static string $resource = NivelAprobacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
