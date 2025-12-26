<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar Pago'),
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
    
}
