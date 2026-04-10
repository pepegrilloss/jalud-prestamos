<?php

namespace App\Filament\Resources\GenerarCreditoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProposicionCredito;
use Illuminate\Database\Eloquent\Builder;

class CreditosPorGenerarStats extends BaseWidget
{
    protected function getStats(): array
    {
        $query = ProposicionCredito::where('Estado', 'APROBADO')
            ->whereDoesntHave('credito');

        // Aplicar filtro de sede si no es admin
        $user = auth()->user();
        if ($user && !$user->esAdmin() && $user->SedeID) {
            $query->where('SedeID', $user->SedeID);
        }

        $totalMonto = (clone $query)->sum('MontoTotal');
        $cantidad = (clone $query)->count();

        return [
            Stat::make('Total por Generar', 'S/ ' . number_format($totalMonto, 2))
                ->description($cantidad . ' créditos aprobados pendientes')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'font-bold text-2xl']),

            Stat::make('Cantidad de Créditos', $cantidad)
                ->description('Listos para formalizar')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
