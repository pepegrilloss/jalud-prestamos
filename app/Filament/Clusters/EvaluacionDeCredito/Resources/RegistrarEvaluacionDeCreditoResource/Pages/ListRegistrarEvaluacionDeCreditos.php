<?php

namespace App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;

use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistrarEvaluacionDeCreditos extends ListRecords
{
    protected static string $resource = RegistrarEvaluacionDeCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
