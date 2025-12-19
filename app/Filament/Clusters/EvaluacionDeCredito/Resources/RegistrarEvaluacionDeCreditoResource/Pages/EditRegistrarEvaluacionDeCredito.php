<?php

namespace App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;

use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistrarEvaluacionDeCredito extends EditRecord
{
    protected static string $resource = RegistrarEvaluacionDeCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
