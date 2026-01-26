<?php

namespace App\Filament\Resources\EvaluacionRiesgoResource\Pages;

use App\Filament\Resources\EvaluacionRiesgoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEvaluacionRiesgo extends CreateRecord
{
    protected static string $resource = EvaluacionRiesgoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
