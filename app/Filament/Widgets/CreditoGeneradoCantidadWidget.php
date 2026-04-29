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

        $description = $fecha 
            ? 'En la fecha seleccionada' 
            : 'Todos los registros';

        return [
            Stat::make('Cantidad de Totales', $cantidad . ' créditos')
                ->description($description)
                ->descriptionIcon('heroicon-m-document-check')
                ->color($fecha ? 'warning' : 'primary')
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
