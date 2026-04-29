<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class ExcedenteOficinaStatsWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\ExcedenteResource\Pages\ListExcedentes::class;
    }

    protected function getStats(): array
    {
        $totalCajero = $this->getPageTableQuery()->where('TipoExcedente', 'SOBRANTE_CAJERO')->sum('Monto');

        return [
            Stat::make('Registro de Excedentes En Oficina', 'S/ ' . number_format($totalCajero, 2))
                ->color('warning')
                ->description('Efectivo en oficina'),
        ];
    }
}
