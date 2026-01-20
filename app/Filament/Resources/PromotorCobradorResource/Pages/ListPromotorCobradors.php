<?php

namespace App\Filament\Resources\PromotorCobradorResource\Pages;

use App\Filament\Resources\PromotorCobradorResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotorCobradors extends ListRecords
{
    protected static string $resource = PromotorCobradorResource::class;

    public function getTitle(): string
    {
        $title = 'Promotores y Cobradores';
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
