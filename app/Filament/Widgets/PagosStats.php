<?php

namespace App\Filament\Widgets;

use App\Models\Pago;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PagosStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        // Base query for Pagos
        $query = Pago::query();

        if ($zonaID) {
            $query->whereHas('cuota.credito.proposicion', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID);
            });
        }

        // 1. Total Pagos del Día (cantidad)
        $pagosDelDia = (clone $query)
            ->whereDate('FechaPago', now())
            ->count();

        // 2. Sumatoria Monto Pagos del Día
        $montoPagosDelDia = (clone $query)
            ->whereDate('FechaPago', now())
            ->sum('MontoPagado');

        // 3. Total Monto Pagado en el Mes
        $montoPagosMes = (clone $query)
            ->whereMonth('FechaPago', now()->month)
            ->whereYear('FechaPago', now()->year)
            ->sum('MontoPagado');

        return [
            Stat::make('Pagos del Día', $pagosDelDia)
                ->description('Transacciones registradas hoy')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Monto Pagado Hoy', 'S/ ' . number_format($montoPagosDelDia, 2, '.', ','))
                ->description('Sumatoria de pagos del día')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Monto Pagado Mes', 'S/ ' . number_format($montoPagosMes, 2, '.', ','))
                ->description('Total acumulado del mes')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
        ];
    }
}
