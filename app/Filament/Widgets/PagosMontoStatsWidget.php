<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\Auth;

class PagosMontoStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 2;
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
        
        $montoPagos = $query->sum('MontoPagado');
        $labelMonto = $isFilteredByDate ? 'Monto en Rango' : 'Monto Pagado Hoy';
        $descMonto = $isFilteredByDate ? 'Sumatoria total filtrada' : 'Sumatoria de pagos del día';

        return [
            Stat::make($labelMonto, 'S/ ' . number_format($montoPagos, 2, '.', ','))
                ->description($descMonto)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
        ];
    }
}
