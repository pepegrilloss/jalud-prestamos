<?php

namespace App\Filament\Resources\GenerarCreditoResource\Widgets;

use App\Models\ProposicionCredito;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProposicionCreditoStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Propuesto', 'S/ ' . number_format(
                ProposicionCredito::where('Estado', 'APROBADO')
                    ->whereDoesntHave('credito')
                    ->sum('MontoTotal'), 2
            ))
                ->description('Suma de todos los montos totales propuestos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
