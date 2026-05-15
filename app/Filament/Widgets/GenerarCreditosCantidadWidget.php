<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProposicionCredito;

class GenerarCreditosCantidadWidget extends BaseWidget
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
        
        $cantidad = (clone $query)->count();

        return [
            Stat::make('Cantidad de Créditos', $cantidad)
                ->description('Listos para formalizar')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),
        ];
    }
}
