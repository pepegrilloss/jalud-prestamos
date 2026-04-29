<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProposicionCredito;

class GenerarCreditoTotalPorGenerarStats extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $query = ProposicionCredito::where('Estado', 'APROBADO')
            ->whereDoesntHave('credito');

        // Aplicar filtro de sede si no es admin
        $user = auth()->user();
        if ($user && !$user->esAdmin() && $user->SedeID) {
            $query->where('SedeID', $user->SedeID);
        }

        $totalMonto = $query->get()->sum(function ($record) {
            return (float) ($record->MontoTotal ?? 0) + ((float) ($record->MontoTotal ?? 0) * ((float) ($record->TasaInteres ?? 0) / 100));
        });

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
