<?php

namespace App\Filament\Resources\EvaluacionRiesgoResource\Pages;

use App\Filament\Resources\EvaluacionRiesgoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvaluacionRiesgo extends EditRecord
{
    protected static string $resource = EvaluacionRiesgoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
