<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\DatePicker;

class ListCreditos extends ListRecords
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Créditos Generados';

    public ?string $fechaFiltro = null;

    public function mount(): void
    {
        parent::mount();
        $defaultDate = now()->toDateString();
        $hasDataToday = \App\Models\Credito::whereDate('FechaGeneracion', $defaultDate)->exists();
        
        $this->fechaFiltro = session()->get('creditos_fecha_filtro_v2', request()->query('fechaSeleccionada', $hasDataToday ? $defaultDate : null));
    }

    #[\Livewire\Attributes\On('updateFechaCreditos')]
    public function updateFechaFiltro($fecha)
    {
        $this->fechaFiltro = $fecha;
        $this->dispatch('refreshTable');
    }

    #[\Livewire\Attributes\On('abrirModalFiltroFecha')]
    public function abrirModalFiltro()
    {
        $this->mountAction('filtrar_fecha');
    }

    public function getTitle(): string
    {
        $title = 'Créditos Generados';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CreditoGeneradoTotalWidget::class,
            \App\Filament\Widgets\CreditoGeneradoCantidadWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filtrar_fecha')
                ->label('Filtrar por Fecha')
                ->icon('heroicon-m-calendar-days')
                ->color('info')
                ->modalHeading('Seleccionar Fecha para Filtrar')
                ->modalWidth('sm')
                ->form([
                    DatePicker::make('fecha')
                        ->label('Fecha de Generación')
                        ->default($this->fechaFiltro)
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data) {
                    $this->fechaFiltro = $data['fecha'];
                    session()->put('creditos_fecha_filtro_v2', $data['fecha']);
                    $this->dispatch('refreshTable');
                }),
            
            Actions\Action::make('limpiar_filtro')
                ->label('Limpiar Filtro')
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->action(function () {
                    $this->fechaFiltro = null;
                    session()->forget('creditos_fecha_filtro_v2');
                    $this->dispatch('refreshTable');
                })
                ->visible(fn() => !empty($this->fechaFiltro)),
        ];
    }
}
