<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ExcedenteYapeStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\ExcedenteResource\Pages\ListExcedentes::class;
    }

    protected function getStats(): array
    {
        $totalYape = $this->getPageTableQuery()->where('TipoExcedente', 'YAPE_TRANSFERENCIA')->sum('Monto');

        return [
            Stat::make('Total Yape/Transf.', 'S/ ' . number_format($totalYape, 2))
                ->color('info')
                ->description('Sin identificar'),
        ];
    }
}
