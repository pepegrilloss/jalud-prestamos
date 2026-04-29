<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ExcedentePromotorStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\ExcedenteResource\Pages\ListExcedentes::class;
    }

    protected function getStats(): array
    {
        $totalPromotor = $this->getPageTableQuery()->where('TipoExcedente', 'SOBRANTE_PROMOTOR')->sum('Monto');

        return [
            Stat::make('Sobrante Promotores', 'S/ ' . number_format($totalPromotor, 2))
                ->color('success')
                ->description('Efectivo en campo'),
        ];
    }
}
