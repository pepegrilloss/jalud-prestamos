<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\AperturaCierreDia;
use App\Filament\Widgets\PagosStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPagos extends ListRecords
{
    protected static string $resource = PagoResource::class;

    public function getTitle(): string
    {
        $title = 'Pagos';
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
                ->label('Registrar Pago');
        }
        
        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PagosStats::class,
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
