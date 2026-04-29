<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProposicionCredito;

class GenerarCreditoCantidadCreditosStats extends BaseWidget
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

        $cantidad = $query->count();

        return [
            Stat::make('Cantidad de Créditos', $cantidad)
                ->description('Listos para formalizar')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),
        ];
    }
}
