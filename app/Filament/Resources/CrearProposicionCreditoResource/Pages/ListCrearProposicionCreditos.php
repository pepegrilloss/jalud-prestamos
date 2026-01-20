<?php

namespace App\Filament\Resources\CrearProposicionCreditoResource\Pages;

use App\Filament\Resources\CrearProposicionCreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;

class ListCrearProposicionCreditos extends ListRecords
{
    protected static string $resource = CrearProposicionCreditoResource::class;

    public function getTitle(): string
    {
        $title = 'Proposiciones';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }
}