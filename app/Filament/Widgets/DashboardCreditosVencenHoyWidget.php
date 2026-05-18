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
        return auth()->user()->can('widget_' . class_basename(static::class));
    }
    protected function getStats(): array
    {
        $user = Auth::user();
        

        $fechaReferencia = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $creditosQuery = \App\Models\Credito::where('Activo', true)
            ->whereDate('FechaVencimiento', $fechaReferencia->toDateString())
            ->where('EstatusCreditoFinal', '!=', 'SALDADO')
            ->whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0)
                  ->where('SaldoPendiente', '>', 0);
            });

        if (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
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