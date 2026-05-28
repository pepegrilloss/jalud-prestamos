<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardMisPrestamosActivosWidget extends BaseWidget
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
        $esTodas = false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            $filter = session('gerencia_dashboard_sede', '0');
            if ($filter === '0' || $filter === '' || $filter === null) {
                $esTodas = true;
            } else {
                $sedeIdOverride = (int) $filter;
            }
        }

        $promotorCobrador = $user->promotorCobrador;
        $zonaID = ($sedeIdOverride || $esTodas) ? null : ($promotorCobrador?->ZonaID ?? null);

        $creditosQuery = \App\Models\Credito::where('Activo', true)
            ->when($sedeIdOverride, fn($q) => $q->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride))
            ->when($esTodas, fn($q) => $q->withoutGlobalScope('sede'))
            ->whereHas('proposicion', function ($q) use ($zonaID, $sedeIdOverride, $esTodas) {
                if ($sedeIdOverride || $esTodas) {
                    $q->withoutGlobalScope('sede');
                }
                $q->where('FueRefinanciada', 0);
                if ($zonaID) {
                    $q->where('ZonaID', $zonaID);
                }
            });
        $prestamosActivos = $creditosQuery->count();

        return [
            Stat::make('Mis Préstamos Activos', $prestamosActivos)
                ->description('Créditos actualmente en curso')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
        ];
    }
}