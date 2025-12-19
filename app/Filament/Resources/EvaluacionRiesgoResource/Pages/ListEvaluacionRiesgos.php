<?php

namespace App\Filament\Resources\EvaluacionRiesgoResource\Pages;

use App\Filament\Resources\EvaluacionRiesgoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvaluacionRiesgos extends ListRecords
{
    protected static string $resource = EvaluacionRiesgoResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
