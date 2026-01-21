<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;


use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        // Para Promotores Cobradores: solo mostrar CustomAccountWidget y ClienteProposicionStats
        if (auth()->check() && auth()->user()->hasRole('Promotor Cobrador')) {
            return [
                \App\Filament\Widgets\CustomAccountWidget::class,
                ClienteProposicionStats::class,
            ];
        }

        // Para otros roles: mostrar todos los widgets
        return [
            \App\Filament\Widgets\CustomAccountWidget::class,
            ClienteProposicionStats::class,
            \App\Filament\Widgets\CobranzaStats::class,
            \App\Filament\Widgets\ProposicionesStats::class,
        ];
    }
}
