<?php

namespace App\Filament\Resources\ExcedenteResource\Widgets;

use App\Filament\Resources\ExcedenteResource\Pages\ListExcedentes;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ExcedentesStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListExcedentes::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        // 1. Total Sobrante Yape/Transferencia
        $totalYape = (clone $query)->where('TipoExcedente', 'YAPE_TRANSFERENCIA')->sum('Monto');
        
        // 2. Total Sobrante Promotor
        $totalPromotor = (clone $query)->where('TipoExcedente', 'SOBRANTE_PROMOTOR')->sum('Monto');
        
        // 3. Total Sobrante Cajero
        $totalCajero = (clone $query)->where('TipoExcedente', 'SOBRANTE_CAJERO')->sum('Monto');

        // 4. Gran Total
        $totalGeneral = (clone $query)->sum('Monto');

        return [
            Stat::make('Total Yape/Transf.', 'S/ ' . number_format($totalYape, 2))
                ->color('info')
                ->description('Sin identificar'),
            Stat::make('Sobrante Promotores', 'S/ ' . number_format($totalPromotor, 2))
                ->color('success')
                ->description('Efectivo en campo'),
            Stat::make('Registro de Excedentes En Oficina', 'S/ ' . number_format($totalCajero, 2))
                ->color('warning')
                ->description('Efectivo en oficina'),
            Stat::make('Total Excedentes', 'S/ ' . number_format($totalGeneral, 2))
                ->color('primary')
                ->description('Sumatoria total filtrada'),
        ];
    }
}
