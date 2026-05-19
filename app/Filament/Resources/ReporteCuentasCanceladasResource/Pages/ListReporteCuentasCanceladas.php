<?php

namespace App\Filament\Resources\ReporteCuentasCanceladasResource\Pages;

use App\Filament\Resources\ReporteCuentasCanceladasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ListReporteCuentasCanceladas extends ListRecords
{
    protected static string $resource = ReporteCuentasCanceladasResource::class;

    public function getTitle(): string
    {
        return 'Cuentas Canceladas en el Día';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF Cuentas Canceladas')
                ->icon('heroicon-o-document-arrow-down')
                ->form([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Seleccionar Fecha')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if (!isset($data['fecha'])) {
                        return;
                    }
                    
                    $fecha = is_string($data['fecha']) ? $data['fecha'] : $data['fecha']->format('Y-m-d');
                    
                    // Validar si hay cuentas canceladas para la fecha seleccionada
                    $canceladas = \App\Models\ProposicionCredito::where('SaldoPendiente', 0)
                        ->whereHas('credito', function ($q) use ($fecha) {
                            $q->whereDate('FechaSaldamiento', '=', $fecha);
                        })
                        ->count();
                    
                    if ($canceladas === 0) {
                        Notification::make()
                            ->title('Sin cuentas canceladas')
                            ->body('No hay cuentas canceladas registradas para la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $url = route('cuentas-canceladas.view', ['fecha' => $fecha]);
                    
                    // Abrir en nueva ventana
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                })
                ->color('danger'),
        ];
    }
}
