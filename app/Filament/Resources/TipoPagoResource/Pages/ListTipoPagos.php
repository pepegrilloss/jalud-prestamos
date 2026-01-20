<?php

namespace App\Filament\Resources\TipoPagoResource\Pages;

use App\Filament\Resources\TipoPagoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListTipoPagos extends ListRecords
{
    protected static string $resource = TipoPagoResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos de Pago';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
