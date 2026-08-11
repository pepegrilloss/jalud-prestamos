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
use App\Models\AperturaCierreDia;

class BalanceDiarioModal extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    #[Livewire\Attributes\On('abrirBalanceDiario')]
    public function abrirModal(): void
    {
        if (! auth()->user()?->can('balance_diario')) {
            return;
        }

        $this->mountAction('generarReporte');
    }

    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('BALANCE DIARIO')
            ->modalDescription('Seleccione la fecha y el formato para generar el reporte de su sede.')
            ->form([
                DatePicker::make('fecha')
                    ->label('Día a Procesar')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
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
                $fecha = $data['fecha'];
                $formato = $data['formato'] ?? 'pdf';
                $user = auth()->user();
                $sedeId = $user->getEffectiveSedeId();

                if ($formato === 'pdf') {
                    $apertura = AperturaCierreDia::withoutGlobalScopes()
                        ->where('Fecha', $fecha)
                        ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
                        ->first();

                    $params = ['fecha' => \Carbon\Carbon::parse($fecha)->format('Y-m-d')];
                    if ($apertura) {
                        $params['id'] = $apertura->AperturaCierreDiaID;
                        $params['sede_id'] = $sedeId;
                    } else {
                        $params['sede_id'] = $sedeId ?? '0';
                    }

                    $url = route('reporte-diario.pdf', $params);
                } else {
                    $url = route('reporte-diario.excel', [
                        'fecha' => \Carbon\Carbon::parse($fecha)->format('Y-m-d'),
                        'sede_id' => $sedeId ?? '0',
                    ]);
                }

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('md');
    }

    public function render()
    {
        return view('livewire.balance-diario-modal');
    }
}
