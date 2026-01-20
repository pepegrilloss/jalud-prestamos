<?php

namespace App\Filament\Resources\TipoCreditoResource\Pages;

use App\Filament\Resources\TipoCreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiposCredito extends ListRecords
{
    protected static string $resource = TipoCreditoResource::class;

    public function getTitle(): string
    {
        $title = 'Tipos de Crédito';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
