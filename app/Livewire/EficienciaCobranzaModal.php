<?php

namespace App\Livewire;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

class EficienciaCobranzaModal extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    #[\Livewire\Attributes\On('abrirEficienciaCobranza')]
    public function abrirModal(): void
    {
        if (! auth()->user()?->can('eficiencia_cobranza')) {
            return;
        }

        $this->mountAction('generarReporte');
    }

    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('EFICIENCIA DE COBRANZA')
            ->modalDescription('Seleccione el rango de fechas para generar el reporte Excel.')
            ->form([
                DatePicker::make('fecha_desde')
                    ->label('Desde')
                    ->required()
                    ->default(now()->startOfMonth())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
                DatePicker::make('fecha_hasta')
                    ->label('Hasta')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('fecha_desde'),
            ])
            ->modalSubmitActionLabel('Descargar Excel')
            ->modalCancelActionLabel('Salir')
            ->extraModalFooterActions([
                Action::make('descargarDetalle')
                    ->label('Detalle por fecha')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading('DETALLE DE EFICIENCIA DE COBRANZA')
                    ->modalDescription('Descargue la lista numerada de clientes que cancelaron, no pagaron y salieron de cartera.')
                    ->form([
                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->required()
                            ->default(today())
                            ->native(false)
                            ->maxDate(today())
                            ->displayFormat('d/m/Y'),
                    ])
                    ->modalSubmitActionLabel('Descargar detalle')
                    ->action(function (array $data) {
                        $fecha = Carbon::parse($data['fecha'])->format('Y-m-d');
                        $sedeId = auth()->user()?->getEffectiveSedeId();
                        $url = route('reporte-eficiencia-cobranza.detalle.excel', [
                            'fecha' => $fecha,
                            'sede_id' => $sedeId ?? '0',
                        ]);

                        $this->js("window.open('{$url}', '_blank')");
                    }),
            ])
            ->action(function (array $data) {
                $desde = Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                $hasta = Carbon::parse($data['fecha_hasta'])->format('Y-m-d');
                $sedeId = auth()->user()?->getEffectiveSedeId();

                $url = route('reporte-eficiencia-cobranza.excel', [
                    'fecha_desde' => $desde,
                    'fecha_hasta' => $hasta,
                    'sede_id' => $sedeId ?? '0',
                ]);

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('md');
    }

    public function render()
    {
        return view('livewire.eficiencia-cobranza-modal');
    }
}
