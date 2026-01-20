<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    public function getTitle(): string
    {
        $title = 'Clientes';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        if (AperturaCierreDia::estaAbierto()) {
            $actions[] = Actions\CreateAction::make()
                ->label('Nuevo Cliente');
        }
        
        return $actions;
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            $pcId = auth()->user()->PromotorCobradorID ?? \App\Models\PromotorCobrador::where('Codigo', auth()->user()->username)
                ->orWhere('Descripcion', auth()->user()->name)
                ->value('PromotorCobradorID');

            if ($pcId) {
                return $query->where('PromotorCobradorID', $pcId);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
}