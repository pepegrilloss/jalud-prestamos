<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use App\Models\Pago;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class PagosStats extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 2;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\PagoResource\Pages\ListPagos::class;
    }

    protected function getStats(): array
    {
        // Obtenemos la consulta de la tabla (que ya incluye filtros)
        $query = $this->getPageTableQuery();
        
        // Obtenemos los filtros actuales para determinar si hay filtrado por fecha
        $filters = $this->tableFilters['filtros_dinamicos'] ?? [];
        $camposActivos = $filters['campos_activos'] ?? [];
        $isFilteredByDate = in_array('fecha', $camposActivos) && (!empty($filters['FechaDesde']) || !empty($filters['FechaHasta']));
        
        // Si NO hay filtro de fecha seleccionado, restringir a pagos del día
        $statsQuery = clone $query;
        if (!$isFilteredByDate) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $fechaHoy = $fechaAbierta ? $fechaAbierta->toDateString() : now()->toDateString();
            $statsQuery->whereDate('pago.FechaPago', $fechaHoy);
        }
        
        // 1. Cantidad de Pagos (Filtrados o del día)
        $pagosCount = (clone $statsQuery)->count();

        // 2. Monto Pagado (Filtrado o del día)
        $montoPagos = (clone $statsQuery)->sum('MontoPagado');

        // 3. Monto Pagado Mes (Respetando otros filtros pero enfocado al mes actual)
        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        $monthQuery = Pago::query()
            ->whereMonth('FechaPago', now()->month)
            ->whereYear('FechaPago', now()->year);

        if ($zonaID) {
            $monthQuery->whereHas('cuota.credito.proposicion', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID);
            });
        }
        
        if (!empty($filters['SedeID'])) {
            $monthQuery->where('pago.SedeID', $filters['SedeID']);
        }

        if (!empty($filters['ClienteID'])) {
            $monthQuery->whereHas('cuota.credito.proposicion', function ($q) use ($filters) {
                $q->where('ClienteID', $filters['ClienteID']);
            });
        }

        if (!empty($filters['TipoPago']) && is_array($filters['TipoPago'])) {
            $monthQuery->whereIn('pago.TipoPago', $filters['TipoPago']);
        }

        // Misma lógica de inclusión que la tabla en PagoResource
        $monthQuery->where(function ($q) {
            $q->where('pago.EsPagoAutomatico', 0)
                ->orWhere(function ($subQ) {
                    $subQ->where('pago.EsPagoAutomatico', 1)
                        ->where('pago.TipoConcepto', '!=', 'C');
                });
        });

        $montoPagosMes = $monthQuery->sum('MontoPagado');

        // Etiquetas dinámicas según el estado del filtro
        $labelPagos = $isFilteredByDate ? 'Pagos en Rango' : 'Pagos del Día';
        $labelMonto = $isFilteredByDate ? 'Monto en Rango' : 'Monto Pagado Hoy';
        $descPagos = $isFilteredByDate ? 'Total de transacciones filtradas' : 'Transacciones registradas hoy';
        $descMonto = $isFilteredByDate ? 'Sumatoria total filtrada' : 'Sumatoria de pagos del día';

        return [
            Stat::make($labelPagos, $pagosCount)
                ->description($descPagos)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make($labelMonto, 'S/ ' . number_format($montoPagos, 2, '.', ','))
                ->description($descMonto)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Monto Pagado Mes', 'S/ ' . number_format($montoPagosMes, 2, '.', ','))
                ->description('Total acumulado del mes' . ($isFilteredByDate ? ' (Según selección)' : ''))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
        ];
    }
}
