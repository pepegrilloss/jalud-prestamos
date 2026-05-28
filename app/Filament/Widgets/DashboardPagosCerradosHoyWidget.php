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
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getListeners(): array
    {
        return ['sedeFilterChanged' => '$refresh'];
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $sedeIdOverride = null;
        $esTodas = false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            $filter = session('gerencia_dashboard_sede', '0');
            if ($filter === '0' || $filter === '' || $filter === null) {
                $esTodas = true;
            } else {
                $sedeIdOverride = (int) $filter;
            }
        }

        $fecha = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $pagosQuery = \App\Models\Pago::where('Activo', true)
            ->where('EsPagoAutomatico', 0)
            ->whereDate('FechaPago', $fecha);

        if ($esTodas) {
            $pagosQuery->withoutGlobalScope('sede');
        } elseif ($sedeIdOverride !== null) {
            $pagosQuery->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } elseif (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
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