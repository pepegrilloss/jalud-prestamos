<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

use App\Models\HistorialExoneracion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExoneracionesStatsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $totalExonerado = HistorialExoneracion::sum('MontoExonerado');
        $totalExoneraciones = HistorialExoneracion::count();
        
        $exoneracionesInteres = HistorialExoneracion::where('TipoExoneracion', 'I')->sum('MontoExonerado');
        $exoneracionesMora = HistorialExoneracion::where('TipoExoneracion', 'M')->sum('MontoExonerado');

        return [
            Stat::make('Total Exonerado', '$' . number_format($totalExonerado, 2))
                ->description('Monto total')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Total Exoneraciones', $totalExoneraciones)
                ->description('Solicitudes aprobadas')
                ->icon('heroicon-o-check-circle')
                ->color('info'),
            Stat::make('Exoneración Intereses', '$' . number_format($exoneracionesInteres, 2))
                ->description('Intereses condonados')
                ->icon('heroicon-o-percent-badge')
                ->color('warning'),
            Stat::make('Exoneración Mora', '$' . number_format($exoneracionesMora, 2))
                ->description('Mora condonada')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger'),
        ];
    }
}
