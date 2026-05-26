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

        $fecha = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $pagosQuery = \App\Models\Pago::where('Activo', true)
            ->where('EsPagoAutomatico', 0)
            ->whereDate('FechaPago', $fecha);

        if ($sedeIdOverride) {
            $pagosQuery->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } elseif ($zonaID) {
            $pagosQuery->whereHas('cuota.credito.proposicion', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID);
            });
        } elseif (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
            $pagosQuery->where('SedeID', $user->getEffectiveSedeId());
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