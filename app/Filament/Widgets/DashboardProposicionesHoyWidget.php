<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;


class DashboardProposicionesHoyWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    
    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }
    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorID = $user->PromotorCobradorID ?? null;

        $query = \App\Models\ProposicionCredito::query();
        if ($promotorID) {
            $query->whereHas('cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }

        $fecha = \App\Services\DateFieldResolver::getFechaAbierta() ?? now();

        $proposicionesHoy = (clone $query)->whereDate('FechaPropuesta', $fecha)->count();

        return [
            Stat::make('Proposiciones de Hoy', $proposicionesHoy)
                ->description('Solicitudes registradas hoy')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info')
        ];
    }
}