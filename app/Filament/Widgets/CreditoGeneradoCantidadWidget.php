<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoCantidadWidget extends BaseWidget
{
    use HasWidgetShield;

    public ?string $fechaFiltro = null;

    protected int | string | array $columnSpan = 1;

    public function mount(): void
    {
        $this->fechaFiltro = session('creditos_fecha_filtro_v2');
    }

    public function updatedFechaFiltro($value)
    {
        session()->put('creditos_fecha_filtro_v2', $value);
        $this->dispatch('updateFechaCreditos', fecha: $value);
    }

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $fecha = $this->fechaFiltro;

        $query = Credito::whereHas('proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            });
        
        if ($fecha) {
            $query->whereDate('FechaGeneracion', $fecha);
        }

        $cantidad = $query->count();

        // Generamos un input nativo de fecha, que se ve como un texto/pequeño calendario
        $htmlInput = '
            <div class="flex items-center gap-1 mt-1 z-50 relative">
                <span class="text-sm font-medium">Filtrar: </span>
                <input type="date" wire:model.live="fechaFiltro" 
                    class="text-sm border-gray-300 rounded-md py-1 px-2 text-gray-700 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 cursor-pointer"
                    style="max-width: 140px;">
                ' . ($fecha ? '<button type="button" wire:click="$set(\'fechaFiltro\', null)" class="text-xs text-red-500 hover:text-red-700 ml-1" title="Limpiar filtro">✖</button>' : '') . '
            </div>
        ';

        return [
            Stat::make('Cantidad de Totales', $cantidad . ' créditos')
                ->description(new \Illuminate\Support\HtmlString($htmlInput))
                ->color($fecha ? 'warning' : 'primary'),
        ];
    }
}
