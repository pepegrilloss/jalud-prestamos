<?php

namespace App\Filament\Resources\SubGiroResource\Pages;

use App\Filament\Resources\SubGiroResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSubGiro extends CreateRecord
{
    protected static string $resource = SubGiroResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
