<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\Auth;

class PagosMontoMesStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\PagoResource\Pages\ListPagos::class;
    }

    protected function getStats(): array
    {
        $filters = $this->tableFilters['filtros_dinamicos'] ?? [];
        $camposActivos = $filters['campos_activos'] ?? [];
        $isFilteredByDate = in_array('fecha', $camposActivos) && (!empty($filters['FechaDesde']) || !empty($filters['FechaHasta']));

        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        $monthQuery = \App\Models\Pago::query()
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

        $monthQuery->where(function ($q) {
            $q->where('pago.EsPagoAutomatico', 0)
                ->orWhere(function ($subQ) {
                    $subQ->where('pago.EsPagoAutomatico', 1)
                        ->where('pago.TipoConcepto', '!=', 'C');
                });
        });

        $montoPagosMes = $monthQuery->sum('MontoPagado');

        return [
            Stat::make('Monto Pagado Mes', 'S/ ' . number_format($montoPagosMes, 2, '.', ','))
                ->description('Total acumulado del mes' . ($isFilteredByDate ? ' (Según selección)' : ''))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary')
        ];
    }
}
