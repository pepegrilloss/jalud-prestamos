<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProposicionCredito;

class GenerarCreditosTotalWidget extends BaseWidget
{
    use HasWidgetShield;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $query = ProposicionCredito::where('Estado', 'APROBADO')
            ->whereDoesntHave('credito');

        // Aplicar filtro de sede si no es admin
        $user = auth()->user();
        if ($user && !$user->isPrivileged() && $user->SedeID) {
            $query->where('SedeID', $user->SedeID);
        }

        // OPTIMIZACIÓN: calcular el total en SQL en lugar de cargar todos los registros.
        // Fórmula equivalente a MontoTotal + (MontoTotal * TasaInteres/100).
        $totalMonto = (clone $query)->sum(\Illuminate\Support\Facades\DB::raw('ProposicionCredito.MontoTotal * (1 + ProposicionCredito.TasaInteres / 100.0)'));
        
        $cantidad = (clone $query)->count();

        return [
            Stat::make('Total por Generar', 'S/ ' . number_format($totalMonto, 2))
                ->description($cantidad . ' créditos aprobados pendientes')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'font-bold text-2xl']),
        ];
    }
}
