<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardCreditosVencenHoyWidget extends BaseWidget
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

        $fechaReferencia = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $creditosQuery = \App\Models\Credito::where('Activo', true)
            ->whereDate('FechaVencimiento', $fechaReferencia->toDateString())
            ->where('EstatusCreditoFinal', '!=', 'SALDADO')
            ->whereHas('proposicion', function ($q) use ($sedeIdOverride, $esTodas) {
                if ($sedeIdOverride !== null || $esTodas) {
                    $q->withoutGlobalScope('sede');
                }
                $q->where('FueRefinanciada', 0)
                  ->where('SaldoPendiente', '>', 0);
            });

        if ($esTodas) {
            $creditosQuery->withoutGlobalScope('sede');
        } elseif ($sedeIdOverride !== null) {
            $creditosQuery->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } elseif (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
            $creditosQuery->where('SedeID', $user->getEffectiveSedeId());
        }

        $vencenHoy = $creditosQuery->count();

        return [
            Stat::make('Créditos Vencen Hoy', $vencenHoy)
                ->description('Cuotas con vencimiento hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning')
        ];
    }
}