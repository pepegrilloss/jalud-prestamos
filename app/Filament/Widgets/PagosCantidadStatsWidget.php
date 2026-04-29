<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\Auth;

class PagosCantidadStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\PagoResource\Pages\ListPagos::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        $filters = $this->tableFilters['filtros_dinamicos'] ?? [];
        $camposActivos = $filters['campos_activos'] ?? [];
        $isFilteredByDate = in_array('fecha', $camposActivos) && (!empty($filters['FechaDesde']) || !empty($filters['FechaHasta']));
        
        if (!$isFilteredByDate) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $fechaHoy = $fechaAbierta ? $fechaAbierta->toDateString() : now()->toDateString();
            $query->whereDate('pago.FechaPago', $fechaHoy);
        }
        
        $pagosCount = $query->count();
        $labelPagos = $isFilteredByDate ? 'Pagos en Rango' : 'Pagos del Día';
        $descPagos = $isFilteredByDate ? 'Total de transacciones filtradas' : 'Transacciones registradas hoy';

        return [
            Stat::make($labelPagos, $pagosCount)
                ->description($descPagos)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
        ];
    }
}
