<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;


use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CustomAccountWidget::class,
            ClienteProposicionStats::class,
            \App\Filament\Widgets\CobranzaStats::class,
            \App\Filament\Widgets\ProposicionesStats::class,
        ];
    }
}
