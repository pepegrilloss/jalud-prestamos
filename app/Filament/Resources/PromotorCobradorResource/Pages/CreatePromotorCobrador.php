<?php

namespace App\Filament\Resources\PromotorCobradorResource\Pages;

use App\Filament\Resources\PromotorCobradorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotorCobrador extends CreateRecord
{
    protected static string $resource = PromotorCobradorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
