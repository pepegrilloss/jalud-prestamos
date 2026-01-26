<?php

namespace App\Filament\Resources\GiroResource\Pages;

use App\Filament\Resources\GiroResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGiro extends CreateRecord
{
    protected static string $resource = GiroResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
