<?php

namespace App\Filament\Resources\CreditoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditosDelDiaStats extends BaseWidget
{
    protected function getStats(): array
    {
        $fecha = now()->toDateString();
        
        $query = \App\Models\Credito::with('proposicion')
            ->whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            })
            ->whereDate('FechaGeneracion', $fecha);

        $totalMonto = (clone $query)->get()->sum(function($credito) {
            return $credito->proposicion?->MontoTotal ?? 0;
        });

        $cantidad = (clone $query)->count();

        return [
            Stat::make('Créditos Generados Hoy', 'S/ ' . number_format($totalMonto, 2))
                ->description('Día: ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success')
                ->extraAttributes([
                    'class' => 'font-bold text-2xl',
                ]),
            
            Stat::make('Cantidad de Hoy', $cantidad . ' créditos')
                ->description('Generados el día de hoy')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary')
        ];
    }
}
