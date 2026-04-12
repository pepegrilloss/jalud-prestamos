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
        $this->fechaSeleccionada = session()->get('creditos_fecha_filtro', request()->query('fechaSeleccionada', now()->toDateString()));
    }

    // Método que se llama cuando cambia la fecha desde el frontend
    public function updatedFechaSeleccionada(): void
    {
        session()->put('creditos_fecha_filtro', $this->fechaSeleccionada);
        // Emitir un evento para que la tabla (página ListCreditos) también actualice su filtro
        $this->dispatch('updateFechaCreditos', fecha: $this->fechaSeleccionada);
    }

    protected function getStats(): array
    {
        $fecha = $this->fechaSeleccionada ?? now()->toDateString();

        $query = \App\Models\Credito::with('proposicion')
            ->whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            })
            ->whereDate('FechaGeneracion', $fecha);

        $totalMonto = (clone $query)->get()->sum(function($credito) {
            return $credito->proposicion?->MontoTotal ?? 0;
        });

        $cantidad = (clone $query)->count();

        $fechaFormateada = Carbon::parse($fecha)->format('d/m/Y');
        $esHoy = Carbon::parse($fecha)->isToday();
        $labelDia = $esHoy ? 'Hoy' : $fechaFormateada;

        return [
            Stat::make("Créditos Generados {$labelDia}", 'S/ ' . number_format($totalMonto, 2))
                ->description('Día: ' . $fechaFormateada . ' 📅')
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
                ->description($esHoy ? 'Generados el día de hoy' : "Generados el {$fechaFormateada}")
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
