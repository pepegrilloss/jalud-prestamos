<?php

namespace App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource\Pages;

use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoComprobanteGastos extends ListRecords
{
    protected static string $resource = TipoComprobanteGastoResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos de Comprobante (Gastos)';

        if (! AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }

        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => AperturaCierreDia::estaAbierto()),
        ];
    }
}
