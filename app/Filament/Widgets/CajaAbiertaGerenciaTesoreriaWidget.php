<?php

namespace App\Filament\Widgets;

use App\Models\FondoSede;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CajaAbiertaGerenciaTesoreriaWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $fondo = FondoSede::withoutGlobalScope('sede')
            ->whereHas('sede', fn ($query) => $query->where('Nombre', 'like', '%Gerencia%'))
            ->first();

        $saldo = (float) ($fondo?->Saldo ?? 0);

        return [
            Stat::make('Caja Abierta - Gerencia', 'S/ ' . number_format($saldo, 2))
                ->description('Saldo en vivo desde Fondos de Sedes')
                ->descriptionIcon('heroicon-o-building-library')
                ->color($saldo > 0 ? 'success' : 'gray'),
        ];
    }
}
