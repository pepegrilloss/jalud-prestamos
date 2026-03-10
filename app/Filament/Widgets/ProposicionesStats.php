<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

use App\Models\Credito;
use App\Models\ProposicionCredito;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ProposicionesStats extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorID = $user->PromotorCobradorID ?? null;

        // Base query for Proposiciones
        $query = ProposicionCredito::query();

        if ($promotorID) {
            $query->whereHas('cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }

        // 1. Proposiciones Hoy
        $proposicionesHoy = (clone $query)
            ->whereDate('FechaPropuesta', now())
            ->count();

        // 2. Proposiciones Pendientes
        $pendientes = (clone $query)
            ->where('Estado', 'PENDIENTE')
            ->where('Activo', true)
            ->count();

        // 3. Créditos Refinanciados
        // Lógica coincidente con CreditosRefinanciadosResource:
        // Contar Créditos cuya proposición tiene FueRefinanciada = 1
        $refinanciadosQuery = Credito::whereHas('proposicion', function ($q) {
            $q->where('FueRefinanciada', true);
        });

        if ($promotorID) {
            $refinanciadosQuery->whereHas('proposicion.cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }

        $refinanciados = $refinanciadosQuery->count();

        return [
            Stat::make('Proposiciones de Hoy', $proposicionesHoy)
                ->description('Solicitudes registradas hoy')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info'),

            Stat::make('Pendientes de Aprobación', $pendientes)
                ->description('Proposiciones en espera')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Créditos Refinanciados', $refinanciados)
                ->description('Total créditos refinanciados')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
}
