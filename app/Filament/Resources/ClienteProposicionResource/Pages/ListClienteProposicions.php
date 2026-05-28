<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;

use App\Models\ProposicionCredito;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class ListClienteProposicions extends ListRecords
{
    protected static string $resource = ClienteProposicionResource::class;

    public function getTitle(): string
    {
        $title = 'Proposiciones';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_acta')
                ->label('Descargar PDF Acta de Créditos')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->form([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Seleccionar Fecha')
                        ->default(function () {
                            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                            return $fechaAbierta ?? now();
                        })
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
            Action::make('descargar_acta_excel')
                ->label('Descargar Excel Acta de Créditos')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Seleccionar Fecha')
                        ->default(function () {
                            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                            return $fechaAbierta ?? now();
                        })
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if (!isset($data['fecha'])) {
                        return;
                    }
                    
                    $fecha = is_string($data['fecha']) ? $data['fecha'] : $data['fecha']->format('Y-m-d');
                    
                    // Validar si hay proposiciones
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
                    
                    $url = route('acta-creditos.excel', ['fecha' => $fecha]);
                    
                    // js() para abrir y forzar descarga
                    $this->js("window.open('" . addslashes($url) . "', '_self')");
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class,
            \App\Filament\Widgets\DashboardMisClientesActivosWidget::class,
            \App\Filament\Widgets\DashboardMisPrestamosActivosWidget::class,
            \App\Filament\Widgets\DashboardMiTotalPrestadoWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 4,
        ];
    }
}