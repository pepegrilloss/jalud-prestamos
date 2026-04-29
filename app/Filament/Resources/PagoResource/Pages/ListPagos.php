<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;

class ListPagos extends ListRecords
{
    use ExposesTableToWidgets;

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
            \App\Filament\Widgets\PagosCantidadStatsWidget::class,
            \App\Filament\Widgets\PagosMontoStatsWidget::class,
            \App\Filament\Widgets\PagosMontoMesStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 3,
            'lg' => 3,
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            $promotorCobrador = auth()->user()->promotorCobrador;
            
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                // Filtrar pagos que pertenezcan a créditos cuyas proposiciones estén en la zona del promotor
                return $query->whereHas('cuota.credito.proposicion', function (Builder $q) use ($promotorCobrador) {
                    $q->where('ZonaID', $promotorCobrador->ZonaID);
                });
            }

            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
    
}