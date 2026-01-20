<?php

namespace App\Filament\Resources\CreditosRefinanciadosResource\Pages;

use App\Filament\Resources\CreditosRefinanciadosResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListCreditosRefinanciados extends ListRecords
{
    protected static string $resource = CreditosRefinanciadosResource::class;

    public function getTitle(): string
    {
        $title = 'Créditos Refinanciados';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
