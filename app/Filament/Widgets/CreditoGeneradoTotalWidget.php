<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoTotalWidget extends BaseWidget
{
    use HasWidgetShield;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }


    protected function getStats(): array
    {
        $fecha = session('creditos_fecha_filtro_v2');

        $query = Credito::whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            });
        
        if ($fecha) {
            $query->whereDate('FechaGeneracion', $fecha);
        }

        $totalMonto = $query->get()
            ->sum(function($credito) {
                return $credito->proposicion?->MontoTotal ?? 0;
            });

        $description = $fecha 
            ? 'Filtrado: ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y') 
            : 'Histórico Completo 📅';

        return [
            Stat::make('Créditos Generados Totales', 'S/ ' . number_format($totalMonto, 2))
                ->description($description)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($fecha ? 'warning' : 'success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 transition',
                    'wire:click' => 'abrirFiltroFecha',
                ]),
        ];
    }

    public function abrirFiltroFecha()
    {
        $this->dispatch('abrirModalFiltroFecha');
    }
}
