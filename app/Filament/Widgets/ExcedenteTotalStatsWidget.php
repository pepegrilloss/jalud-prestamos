<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ExcedenteTotalStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\ExcedenteResource\Pages\ListExcedentes::class;
    }

    protected function getStats(): array
    {
        $totalGeneral = $this->getPageTableQuery()->sum('Monto');

        return [
            Stat::make('Total Excedentes', 'S/ ' . number_format($totalGeneral, 2))
                ->color('primary')
                ->description('Sumatoria total filtrada'),
        ];
    }
}
