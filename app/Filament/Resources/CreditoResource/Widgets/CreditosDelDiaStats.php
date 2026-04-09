<?php

namespace App\Filament\Resources\CreditoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CreditosDelDiaStats extends BaseWidget
{
    public ?string $fechaFiltro = null;

    #[On('update-fecha-stats')]
    public function updateFecha($fecha)
    {
        $this->fechaFiltro = $fecha;
    }

    protected function getStats(): array
    {
        $fecha = $this->fechaFiltro ?? now()->toDateString();
        
        $query = \App\Models\Credito::with('proposicion')
            ->whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            })
            ->whereDate('FechaGeneracion', $fecha);

        $totalMonto = (clone $query)->get()->sum(function($credito) {
            return $credito->proposicion?->MontoTotal ?? 0;
        });

        $cantidad = (clone $query)->count();

        return [
            Stat::make('Monto Total Generado', 'S/ ' . number_format($totalMonto, 2))
                ->description('Día: ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success')
                ->extraAttributes([
                    'class' => 'text-center font-bold text-2xl',
                ]),
            
            Stat::make('Cantidad de Créditos', $cantidad . ' créditos')
                ->description('En el periodo consultado')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary')
        ];
    }
}
