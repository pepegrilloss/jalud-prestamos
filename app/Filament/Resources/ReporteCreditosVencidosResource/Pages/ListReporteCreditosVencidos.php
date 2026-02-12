<?php

namespace App\Filament\Resources\ReporteCreditosVencidosResource\Pages;

use App\Filament\Resources\ReporteCreditosVencidosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Illuminate\Support\Carbon;

class ListReporteCreditosVencidos extends ListRecords
{
    protected static string $resource = ReporteCreditosVencidosResource::class;

    public function getTitle(): string
    {
        return 'Créditos Vencidos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF Créditos Vencidos')
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
                    
                    // Validar si hay créditos vencidos para la fecha seleccionada
                    $creditos = \App\Models\Credito::where('Activo', 1)
                        ->whereDate('FechaVencimiento', '<=', $fecha)
                        ->whereHas('proposicion', function ($q) {
                            $q->where('SaldoPendiente', '>', 0);
                        })
                        ->count();
                    
                    if ($creditos === 0) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sin créditos vencidos')
                            ->body('No hay créditos vencidos registrados para la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $url = route('creditos-vencidos.view', ['fecha' => $fecha]);
                    
                    // Abrir en nueva ventana
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                })
                ->color('danger'),
        ];
    }
}
