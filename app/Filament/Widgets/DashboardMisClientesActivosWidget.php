<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardMisClientesActivosWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;
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
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            $filter = session('gerencia_dashboard_sede', '0');
            $sedeIdOverride = ($filter === '0' || $filter === '' || $filter === null) ? null : (int) $filter;
        }

        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $sedeIdOverride ? null : ($promotorCobrador?->ZonaID ?? null);

        $clientesQuery = \App\Models\Cliente::where('Activo', true);
        if ($sedeIdOverride) {
            $clientesQuery->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } elseif ($zonaID) {
            $clientesQuery->whereHas('proposiciones', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID)
                    ->whereHas('credito', function ($q2) {
                        $q2->where('Activo', true);
                    });
            });
        }
        $clientesActivos = $clientesQuery->distinct('ClienteID')->count('ClienteID');

        return [
            Stat::make('Mis Clientes Activos', $clientesActivos)
                ->description('Total de clientes activos asignados')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
        ];
    }
}