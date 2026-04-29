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

    #[\Livewire\Attributes\On('updateFechaCreditos')]
    public function syncFechaFiltro($fecha)
    {
        $this->fechaFiltro = $fecha;
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

        $inputId = 'datePickerCantidad';

        $svgIcon = '<svg class="w-4 h-4 ml-1 cursor-pointer pointer-events-auto" onclick="setTimeout(() => { document.getElementById(\''.$inputId.'\').showPicker(); }, 50)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
  <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd" />
  <path fill-rule="evenodd" d="M19.22 8.72a.75.75 0 00-1.06 0l-4.25 4.25-1.72-1.72a.75.75 0 10-1.06 1.06l2.25 2.25a.75.75 0 001.06 0l4.78-4.78a.75.75 0 000-1.06z" clip-rule="evenodd" />
</svg>';

        $text = $fecha ? 'En la fecha seleccionada' : 'Todos los registros';

        $description = '
        <span class="flex items-center">
            <span>'.$text.'</span>
            '.$svgIcon.'
            <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">
        </span>';

        return [
            Stat::make('Cantidad de Totales', $cantidad . ' créditos')
                ->description(new \Illuminate\Support\HtmlString($description))
                ->color($fecha ? 'warning' : 'primary'),
        ];
    }
}
