<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardMisPrestamosActivosWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 1;

    
    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }
    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        $creditosQuery = \App\Models\Credito::where('Activo', true)
            ->whereHas('proposicion', function ($q) use ($zonaID) {
                $q->where('FueRefinanciada', 0);
                if ($zonaID) {
                    $q->where('ZonaID', $zonaID);
                }
            });
        $prestamosActivos = $creditosQuery->count();

        return [
            Stat::make('Mis Préstamos Activos', $prestamosActivos)
                ->description('Créditos actualmente en curso')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
        ];
    }
}