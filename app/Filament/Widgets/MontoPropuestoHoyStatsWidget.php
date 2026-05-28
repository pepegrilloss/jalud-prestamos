<?php

namespace App\Filament\Widgets;

use App\Models\ProposicionCredito;
use App\Services\DateFieldResolver;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MontoPropuestoHoyStatsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getListeners(): array
    {
        return ['sedeFilterChanged' => '$refresh'];
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $sedeIdOverride = null;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            $filter = session('gerencia_dashboard_sede', '0');
            $sedeIdOverride = ($filter === '0' || $filter === '' || $filter === null) ? null : (int) $filter;
        }

        $promotorID = $user->PromotorCobradorID ?? null;

        $query = ProposicionCredito::query();
        if ($sedeIdOverride) {
            $query->withoutGlobalScope('sede')->where('SedeID', $sedeIdOverride);
        } elseif ($promotorID) {
            $query->whereHas('cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        } elseif ($user->getEffectiveSedeId()) {
            $query->where('SedeID', $user->getEffectiveSedeId());
        }

        $fecha = DateFieldResolver::getFechaAbierta() ?? now();

        $totalMonto = (clone $query)->whereDate('FechaPropuesta', $fecha)->sum('MontoTotal');

        return [
            Stat::make('Monto Propuesto Hoy', 'S/ ' . number_format($totalMonto, 2))
                ->description('Suma total de solicitudes registradas hoy')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($totalMonto > 0 ? 'success' : 'gray'),
        ];
    }
}
