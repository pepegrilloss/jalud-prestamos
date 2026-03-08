<?php

namespace App\Filament\Resources\TipoComprobanteResource\Pages;

use App\Filament\Resources\TipoComprobanteResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListTipoComprobantes extends ListRecords
{
    protected static string $resource = TipoComprobanteResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos de Comprobante';
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
