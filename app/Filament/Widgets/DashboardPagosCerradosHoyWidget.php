<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardPagosCerradosHoyWidget extends BaseWidget
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
        

        $fecha = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $pagosQuery = \App\Models\Pago::where('Activo', true)
            ->where('EsPagoAutomatico', 0)
            ->whereDate('FechaPago', $fecha);

        if (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
            $pagosQuery->where('SedeID', $user->getEffectiveSedeId());
        }

        $pagosCount = $pagosQuery->count();

        return [
            Stat::make('Pagos Cerrados Hoy', $pagosCount)
                ->description('Transacciones registradas hoy')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('primary')
        ];
    }
}