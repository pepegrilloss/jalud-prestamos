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
use Filament\Forms\Components\Select;

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
            ->modalDescription('Seleccione la fecha, tipos de cartera y formato.')
            ->form([
                DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
                CheckboxList::make('tipos')
                    ->label('Tipos de Cartera')
                    ->options([
                        'no_vencida' => 'Cartera NO VENCIDA',
                        'vencida'    => 'Cartera VENCIDA',
                        'morosa'     => 'Cartera MOROSA',
                        'pesada'     => 'Cartera PESADA / PÉRDIDA',
                    ])
                    ->default(['no_vencida', 'vencida', 'morosa', 'pesada'])
                    ->required()
                    ->columns(1),
                Select::make('formato')
                    ->label('Formato')
                    ->options([
                        'pdf' => 'PDF',
                        'excel' => 'Excel',
                    ])
                    ->default('pdf')
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Descargar')
            ->modalCancelActionLabel('Salir')
            ->action(function (array $data) {
                $tipos = $data['tipos'] ?? [];

                if (empty($tipos)) {
                    \Filament\Notifications\Notification::make()
                        ->title('Debe seleccionar al menos un tipo de cartera')
                        ->danger()
                        ->send();
                    return;
                }

                $fecha = \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d');
                $formato = $data['formato'] ?? 'pdf';

                $route = $formato === 'pdf' ? 'reporte-cartera.pdf' : 'reporte-cartera.excel';
                $url = route($route, [
                    'fecha' => $fecha,
                    'tipos' => implode(',', $tipos),
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
