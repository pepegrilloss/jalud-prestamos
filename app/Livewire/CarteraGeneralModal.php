<?php

namespace App\Livewire;

use App\Models\Ciudad;
use App\Models\Sede;
use App\Models\Zona;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

class CarteraGeneralModal extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    #[\Livewire\Attributes\On('abrirCarteraGeneral')]
    public function abrirModal(): void
    {
        if (! auth()->user()?->can('reporte_cartera_general')) {
            return;
        }

        $this->mountAction('generarReporte');
    }

    public function generarReporteAction(): Action
    {
        return Action::make('generarReporte')
            ->modalHeading('CARTERA GENERAL')
            ->modalDescription('Seleccione el rango de fechas, ciudad y zona para generar el reporte Excel.')
            ->form([
                DatePicker::make('fecha_desde')
                    ->label('Desde (Fecha de Giro)')
                    ->required()
                    ->default(now()->startOfMonth())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y'),
                DatePicker::make('fecha_hasta')
                    ->label('Hasta (Fecha de Giro)')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->maxDate(today())
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('fecha_desde'),
                Select::make('ciudad_id')
                    ->label('Ciudad')
                    ->options(function () {
                        $sedeId = auth()->user()?->getEffectiveSedeId();

                        return Ciudad::withoutGlobalScopes()
                            ->where('Activo', 1)
                            ->when($sedeId, fn ($q) => $q->where('SedeID', $sedeId))
                            ->orderBy('Nombre')
                            ->pluck('Nombre', 'CiudadID')
                            ->toArray();
                    })
                    ->placeholder('TODOS')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live(),
                Select::make('zona_id')
                    ->label('Zona')
                    ->options(function (callable $get) {
                        $sedeId = auth()->user()?->getEffectiveSedeId();
                        $ciudadId = $get('ciudad_id');

                        return Zona::withoutGlobalScopes()
                            ->where('Activo', 1)
                            ->when($sedeId, fn ($q) => $q->where('SedeID', $sedeId))
                            ->when($ciudadId, fn ($q) => $q->where('CiudadID', $ciudadId))
                            ->orderBy('Nombre')
                            ->pluck('Nombre', 'ZonaID')
                            ->toArray();
                    })
                    ->placeholder('TODOS')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Descargar Excel')
            ->modalCancelActionLabel('Salir')
            ->action(function (array $data) {
                $desde = Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                $hasta = Carbon::parse($data['fecha_hasta'])->format('Y-m-d');
                $sedeId = auth()->user()?->getEffectiveSedeId();

                $url = route('reporte-cartera-general.excel', [
                    'fecha_desde' => $desde,
                    'fecha_hasta' => $hasta,
                    'sede_id' => $sedeId ?? '0',
                    'ciudad_id' => $data['ciudad_id'] ?? '',
                    'zona_id' => $data['zona_id'] ?? '',
                ]);

                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalWidth('md');
    }

    public function render()
    {
        return view('livewire.cartera-general-modal');
    }
}
