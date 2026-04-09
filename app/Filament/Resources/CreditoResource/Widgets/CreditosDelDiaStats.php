<?php

namespace App\Filament\Resources\CreditoResource\Widgets;

use App\Filament\Resources\CreditoResource\Pages\ListCreditos;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CreditosDelDiaStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListCreditos::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        
        // 🚨 Obtener los filtros directamente desde la propiedad inyectada por InteractsWithPageTable
        $filtros = $this->tableFilters ?? [];
        
        $fecha = $filtros['fecha_filtro']['fecha'] ?? null;
        if ($fecha) {
            $query->whereDate('FechaGeneracion', $fecha);
        } elseif (!isset($filtros['fecha_filtro'])) {
            // Si el componente padre apenas cargó y no ha propagado el filtro, forzamos el de por defecto.
            $diaAbierto = \App\Models\AperturaCierreDia::where('EstadoDia', 'ABIERTO')->value('Fecha');
            if ($diaAbierto) {
                $query->whereDate('FechaGeneracion', $diaAbierto);
            }
        }

        // Sumar monto usando get() dado que la colección no será gigantesca por día
        $totalMonto = (clone $query)->with('proposicion')->get()->sum(function($credito) {
            return $credito->proposicion?->MontoTotal ?? 0;
        });

        // Contar cantidad de créditos
        $cantidad = (clone $query)->count();

        return [
            Stat::make('Monto Total Generado', 'S/ ' . number_format($totalMonto, 2))
                ->description('Resultados Filtrados (Por defecto: Día Abierto)')
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
