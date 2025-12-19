<?php

namespace App\Filament\Resources\ClienteProposicionResource\Widgets;

use App\Models\ProposicionCredito;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClienteProposicionStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Créditos Propuestos', 'S/ ' . number_format(
                ProposicionCredito::sum('MontoTotal'), 2
            ))
                ->description('Suma de todos los montos totales propuestos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
