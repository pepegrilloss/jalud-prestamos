<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Cliente'),
        ];
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