<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoCantidadWidget extends BaseWidget
{
    use HasWidgetShield;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $cantidad = Credito::whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            })
            ->count();

        return [
            Stat::make('Cantidad de Totales', $cantidad . ' créditos')
                ->description('Todos los registros')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),
        ];
    }
}
