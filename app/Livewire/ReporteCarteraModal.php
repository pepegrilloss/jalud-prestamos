<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\CheckboxList;

class ReporteCarteraModal extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    #[\Livewire\Attributes\On('abrirReporteCartera')]
    public function abrirModal(): void
    {
        if (! auth()->user()?->can('reporte_cartera')) {
            return;
        }

        $this->mountAction('generarReporte');
    }

    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('REPORTE DE CARTERA')
            ->modalDescription('Seleccione el rango de fechas y los tipos de cartera a incluir en el reporte.')
            ->form([
                DatePicker::make('fecha_desde')
                    ->label('Desde')
                    ->required()
                    ->default(today()->startOfMonth())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
                DatePicker::make('fecha_hasta')
                    ->label('Hasta')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
                CheckboxList::make('tipos')
                    ->label('Tipos de Cartera')
                    ->options([
                        'no_vencida' => '📗 Cartera NO VENCIDA — Créditos que aún no vencen',
                        'vencida'    => '📙 Cartera VENCIDA — Hasta 7 días de vencimiento',
                        'morosa'     => '📕 Cartera MOROSA — De 8 a 180 días de vencimiento',
                        'pesada'     => '⛔ Cartera PESADA / PÉRDIDA — Más de 180 días',
                    ])
                    ->default(['no_vencida', 'vencida', 'morosa', 'pesada'])
                    ->required()
                    ->columns(1)
                    ->descriptions([
                        'no_vencida' => 'Fecha de vencimiento en el futuro (hoy, mañana, etc.)',
                        'vencida'    => 'Máximo 7 días desde su vencimiento',
                        'morosa'     => 'De 8 hasta 180 días desde su vencimiento',
                        'pesada'     => 'De 181 días a más desde su vencimiento',
                    ]),
            ])
            ->modalSubmitActionLabel('Generar PDF')
            ->modalCancelActionLabel('Salir')
            ->action(function (array $data) {
                $fechaDesde = $data['fecha_desde'];
                $fechaHasta = $data['fecha_hasta'];
                $tipos = implode(',', $data['tipos'] ?? []);

                if (empty($data['tipos'])) {
                    \Filament\Notifications\Notification::make()
                        ->title('Debe seleccionar al menos un tipo de cartera')
                        ->danger()
                        ->send();
                    return;
                }

                $url = route('reporte-cartera.pdf', [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'tipos'       => $tipos,
                ]);

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('lg');
    }

    public function render()
    {
        return view('livewire.reporte-cartera-modal');
    }
}
