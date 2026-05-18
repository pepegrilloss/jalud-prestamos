<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use App\Models\ProposicionCredito;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProposicionCreditoStats extends BaseWidget
{
    use HasWidgetShield;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $query = ProposicionCredito::where('Estado', 'APROBADO')
            ->whereDoesntHave('credito');

        if ($user->getEffectiveSedeId()) {
            $query->where('SedeID', $user->getEffectiveSedeId());
        }

        return [
            Stat::make('Total Propuesto', 'S/ ' . number_format(
                $query->sum('MontoTotal'),
                2
            ))
                ->description('Suma de todos los montos totales propuestos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
