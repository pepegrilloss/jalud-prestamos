<?php

namespace App\Filament\Resources\CreditoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CreditosDelDiaStats extends BaseWidget
{
    // Propiedad reactiva de Livewire para la fecha seleccionada
    public ?string $fechaSeleccionada = null;

    public function mount(): void
    {
        $defaultDate = now()->toDateString();
        $hasDataToday = \App\Models\Credito::whereDate('FechaGeneracion', $defaultDate)->exists();
        
        $this->fechaSeleccionada = session()->get('creditos_fecha_filtro_v2', request()->query('fechaSeleccionada', $hasDataToday ? $defaultDate : null));
    }

    // Método que se llama cuando cambia la fecha desde el frontend
    public function updatedFechaSeleccionada(): void
    {
        session()->put('creditos_fecha_filtro_v2', $this->fechaSeleccionada);
        // Emitir un evento para que la tabla (página ListCreditos) también actualice su filtro
        $this->dispatch('updateFechaCreditos', fecha: $this->fechaSeleccionada);
    }

    protected function getStats(): array
    {
        $fecha = !empty($this->fechaSeleccionada) ? $this->fechaSeleccionada : null;

        $query = \App\Models\Credito::with('proposicion')
            ->whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            });

        if ($fecha) {
            $query->whereDate('FechaGeneracion', $fecha);
        }

        $totalMonto = (clone $query)->get()->sum(function($credito) {
            return $credito->proposicion?->MontoTotal ?? 0;
        });

        $cantidad = (clone $query)->count();

        $fechaFormateada = $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '';
        $esHoy = $fecha ? Carbon::parse($fecha)->isToday() : false;
        $labelDia = $fecha ? ($esHoy ? 'Hoy' : $fechaFormateada) : 'Totales';

        return [
            Stat::make("Créditos Generados {$labelDia}", 'S/ ' . number_format($totalMonto, 2))
                ->description($fecha ? 'Día: ' . $fechaFormateada . ' 📅' : 'Histórico Completo 📅')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success')
                ->extraAttributes([
                    'class' => 'font-bold text-2xl cursor-pointer creditos-stat-card',
                    'x-data' => '{}',
                    'x-on:click' => "
                        const input = document.getElementById('widget-date-picker');
                        if (input) { input.showPicker(); }
                    ",
                ]),

            Stat::make("Cantidad de {$labelDia}", $cantidad . ' créditos')
                ->description($fecha ? ($esHoy ? 'Generados el día de hoy' : "Generados el {$fechaFormateada}") : 'Todos los registros')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    /**
     * Inyectar un input date oculto que al cambiar actualiza la propiedad Livewire
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.widgets.creditos-del-dia-stats', [
            'stats' => $this->getStats(),
            'fechaSeleccionada' => $this->fechaSeleccionada,
        ]);
    }
}
