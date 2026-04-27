<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Models\AperturaCierreDia;

class BalanceDiarioModal extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    /**
     * Escucha el evento del sidebar para abrir el modal
     */
    protected $listeners = ['abrirBalanceDiario' => 'abrirModal'];

    public function abrirModal(): void
    {
        $this->mountAction('generarReporte');
    }

    /**
     * Acción que muestra el modal con el datepicker
     */
    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('BALANCE DIARIO')
            ->modalDescription('Seleccione la fecha para generar el reporte de cierre de caja.')
            ->form([
                DatePicker::make('fecha')
                    ->label('Día a Procesar')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
            ])
            ->modalSubmitActionLabel('Aceptar')
            ->modalCancelActionLabel('Salir')
            ->action(function (array $data) {
                $fecha = $data['fecha'];

                // Resolver sede del usuario
                $user = auth()->user();
                $sedeId = $user->esAdmin() ? session('sede_activa') : $user->SedeID;

                // Buscar el registro de apertura/cierre para esa fecha y sede
                $apertura = AperturaCierreDia::withoutGlobalScopes()
                    ->where('Fecha', $fecha)
                    ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
                    ->first();

                $params = ['fecha' => $fecha];

                if ($apertura) {
                    $params['id'] = $apertura->AperturaCierreDiaID;
                } elseif ($sedeId) {
                    $params['sede'] = $sedeId;
                }

                $url = route('reporte-diario.pdf', $params);

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('md');
    }

    public function render()
    {
        return view('livewire.balance-diario-modal');
    }
}
