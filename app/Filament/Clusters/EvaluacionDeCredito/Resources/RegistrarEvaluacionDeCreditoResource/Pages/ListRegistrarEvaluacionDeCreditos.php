<?php

namespace App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;

use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistrarEvaluacionDeCreditos extends ListRecords
{
    protected static string $resource = RegistrarEvaluacionDeCreditoResource::class;

    public function getTitle(): string
    {
        $title = 'Evaluación de Crédito';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
