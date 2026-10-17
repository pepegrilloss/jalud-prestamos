<?php

namespace App\Filament\Resources\TipoIgvResource\Pages;

use App\Filament\Resources\TipoIgvResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ListTipoIgvs extends ManageRecords
{
    protected static string $resource = TipoIgvResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos IGV';

        if (! AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }

        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => TipoIgvResource::dayAllowsGerenciaMutation())
                ->modalWidth('md')
                ->modalHeading('Nuevo Tipo IGV'),
        ];
    }
}
