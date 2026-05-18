<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardMiTotalPrestadoWidget extends BaseWidget
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
        $query = \App\Models\ProposicionCredito::whereHas('credito')
            ->where('EsRefinanciamiento', 0);

        $promotorCobrador = $user->promotorCobrador;
        if ($promotorCobrador && $promotorCobrador->ZonaID) {
            $query->where('ZonaID', $promotorCobrador->ZonaID);
        } elseif (!$user->isPrivileged() && $user->SedeID) {
            $query->where('SedeID', $user->SedeID);
        } elseif ($user->isPrivileged() && $user->getEffectiveSedeId()) {
            $query->where('SedeID', $user->getEffectiveSedeId());
        }

        $totalPrestado = $query->sum('MontoTotal');

        return [
            Stat::make('Mi Total Prestado', 'S/ ' . number_format($totalPrestado, 2))
                ->description('Monto global desembolsado')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
        ];
    }
}