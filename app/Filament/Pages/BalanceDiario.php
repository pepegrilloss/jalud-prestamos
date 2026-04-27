<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Models\AperturaCierreDia;

class BalanceDiario extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Balance Diario';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = '';
    protected static string $view = 'filament.pages.balance-diario';

    /**
     * Al montar la página, abre automáticamente el modal
     */
    public function mount(): void
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
            ->cancelParentActions()
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

                // Abrir PDF en nueva pestaña y regresar al dashboard
                $this->js("window.open('{$url}', '_blank')");
                $this->redirect('/admin');
            })
            ->modalWidth('md')
            ->closeModalByClickingAway(false);
    }

    /**
     * Cuando el modal se cierra con "Salir", regresar al dashboard
     */
    public function mountedActionCallCancelled(): void
    {
        $this->redirect('/admin');
    }
}
