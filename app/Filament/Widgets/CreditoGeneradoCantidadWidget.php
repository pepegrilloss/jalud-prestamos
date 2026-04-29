<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

use Filament\Widgets\Concerns\InteractsWithPageTable;

class CreditoGeneradoCantidadWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageTable;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\CreditoResource\Pages\ListCreditos::class;
    }

    protected function getStats(): array
    {
        $page = $this->getPageTable();
        $fecha = property_exists($page, 'fechaFiltro') ? $page->fechaFiltro : null;

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
