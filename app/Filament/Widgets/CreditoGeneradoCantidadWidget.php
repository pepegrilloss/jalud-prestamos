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

        $description = $fecha 
            ? 'En la fecha seleccionada <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">' 
            : 'Todos los registros <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">';

        return [
            Stat::make('Cantidad de Totales', $cantidad . ' créditos')
                ->description(new \Illuminate\Support\HtmlString($description))
                ->descriptionIcon('heroicon-m-document-check')
                ->color($fecha ? 'warning' : 'primary')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition',
                    'onclick' => 'setTimeout(() => { document.getElementById(\''.$inputId.'\').showPicker(); }, 50)',
                ]),
        ];
    }
}
