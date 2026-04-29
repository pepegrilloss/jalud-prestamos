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
        

        $creditosConUltimaCuota = \App\Models\Cuota::where('Activo', true)
            ->whereNotIn('Estado', ['PAGADA', 'DOMINGO', 'FERIADO'])
            ->whereHas('credito.proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            });

        $cuotasUltimas = $creditosConUltimaCuota->selectRaw('CreditoID, MAX(FechaVencimiento) as FechaVencimiento')
            ->groupBy('CreditoID')
            ->get();

        $vencenHoy = 0;
        foreach ($cuotasUltimas as $cuota) {
            if ($cuota->FechaVencimiento && \Carbon\Carbon::parse($cuota->FechaVencimiento)->isToday()) {
                $vencenHoy++;
            }
        }

        return [
            Stat::make('Créditos Vencen Hoy', $vencenHoy)
                ->description('Cuotas con vencimiento hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning')
        ];
    }
}