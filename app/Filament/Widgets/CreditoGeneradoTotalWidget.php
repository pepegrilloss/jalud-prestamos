<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoTotalWidget extends BaseWidget
{
    use HasWidgetShield;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $totalMonto = Credito::whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            })
            ->get()
            ->sum(function($credito) {
                return $credito->proposicion?->MontoTotal ?? 0;
            });

        return [
            Stat::make('Créditos Generados Totales', 'S/ ' . number_format($totalMonto, 2))
                ->description('Histórico Completo 📅')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
        ];
    }
}
