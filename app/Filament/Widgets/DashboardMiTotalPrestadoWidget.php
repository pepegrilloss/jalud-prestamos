<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardMiTotalPrestadoWidget extends BaseWidget
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

        $query = \App\Models\ProposicionCredito::whereHas('credito', function ($q) use ($sedeIdOverride, $esTodas) {
            if ($sedeIdOverride !== null || $esTodas) {
                $q->withoutGlobalScope('sede');
            }
        })->where('EsRefinanciamiento', 0);

        if ($esTodas) {
            $query->withoutGlobalScope('sede');
        } elseif ($sedeIdOverride !== null) {
            $query->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } else {
            $promotorCobrador = $user->promotorCobrador;
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                $query->where('ZonaID', $promotorCobrador->ZonaID);
            } elseif (!$user->isPrivileged() && $user->SedeID) {
                $query->where('SedeID', $user->SedeID);
            } elseif ($user->isPrivileged() && $user->getEffectiveSedeId()) {
                $query->where('SedeID', $user->getEffectiveSedeId());
            }
        }

        $totalPrestado = $query->sum('MontoTotal');

        return [
            Stat::make('Mi Total Prestado', 'S/ ' . number_format($totalPrestado, 2))
                ->description('Monto global desembolsado')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
        ];
    }
}