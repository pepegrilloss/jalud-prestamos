<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;
use App\Models\ProposicionCredito;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class ListClienteProposicions extends ListRecords
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_acta')
                ->label('Descargar PDF Acta de Créditos')
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
                    
                    // Validar si hay proposiciones para la fecha seleccionada
                    $proposiciones = ProposicionCredito::where('Activo', true)
                        ->whereDate('FechaPropuesta', '=', $fecha)
                        ->count();
                    
                    if ($proposiciones === 0) {
                        Notification::make()
                            ->title('Sin proposiciones')
                            ->body('No hay proposiciones registradas para la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $url = route('acta-creditos.view', ['fecha' => $fecha]);
                    
                    // Usar js() directamente para abrir en nueva ventana
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClienteProposicionStats::class,
        ];
    }
}