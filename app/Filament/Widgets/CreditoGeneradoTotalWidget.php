<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoTotalWidget extends BaseWidget
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

        $totalMonto = $query->get()
            ->sum(function($credito) {
                return $credito->proposicion?->MontoTotal ?? 0;
            });

        $inputId = 'datePickerTotal';

        $description = $fecha 
            ? 'Filtrado: ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y') . ' <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">'
            : 'Histórico Completo 📅 <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">';

        return [
            Stat::make('Créditos Generados Totales', 'S/ ' . number_format($totalMonto, 2))
                ->description(new \Illuminate\Support\HtmlString($description))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($fecha ? 'warning' : 'success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition',
                    'onclick' => 'setTimeout(() => { document.getElementById(\''.$inputId.'\').showPicker(); }, 50)',
                ]),
        ];
    }
}
