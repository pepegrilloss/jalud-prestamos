<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

class ReporteCreditosModal extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    #[Livewire\Attributes\On('abrirReporteCreditos')]
    public function abrirModal(): void
    {
        $this->mountAction('generarReporte');
    }

    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('REPORTE DE CREDITOS')
            ->modalDescription('Seleccione el rango de fechas y el formato para generar el reporte.')
            ->form([
                DatePicker::make('fecha_desde')
                    ->label('Desde')
                    ->required()
                    ->default(now()->startOfMonth())
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('fecha_hasta')
                    ->label('Hasta')
                    ->required()
                    ->default(now())
                    ->native(false)
                    ->displayFormat('d/m/Y'),
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
                $fechaDesde = $data['fecha_desde'];
                $fechaHasta = $data['fecha_hasta'];
                $formato = $data['formato'] ?? 'pdf';

                $user = auth()->user();
                $sedeId = $user->getEffectiveSedeId();

                if ($formato === 'pdf') {
                    $url = route('reporte-creditos.pdf', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                        'sede_id' => $sedeId ?? '0',
                    ]);
                } else {
                    $url = route('reporte-creditos.excel', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                        'sede_id' => $sedeId ?? '0',
                    ]);
                }

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('md');
    }

    public function render()
    {
        return view('livewire.reporte-creditos-modal');
    }
}
