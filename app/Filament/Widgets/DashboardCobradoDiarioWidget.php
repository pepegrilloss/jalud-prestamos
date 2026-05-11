<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardCobradoDiarioWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 1;

    
    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }
    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        $fecha = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $pagosQuery = \App\Models\Pago::where('Activo', true)
            ->where('EsPagoAutomatico', 0)
            ->whereDate('FechaPago', $fecha);

        if ($zonaID) {
            $pagosQuery->whereHas('cuota.credito.proposicion', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID);
            });
        }

        $cobradoDiario = $pagosQuery->sum('MontoPagado');

        return [
            Stat::make('Cobrado Diario', 'S/ ' . number_format($cobradoDiario, 2))
                ->description('Monto total recaudado hoy')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17])
        ];
    }
}