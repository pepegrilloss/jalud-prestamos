<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;

class ListGastos extends ListRecords
{
    protected static string $resource = GastoResource::class;

    public function getTitle(): string
    {
        $title = 'Gastos';
        if (filament()->getCurrentPanel()?->getId() !== 'gerencia' && !AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
            Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    DatePicker::make('fecha')
                        ->label('Fecha')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->placeholder('dd/mm/yyyy')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fecha = \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d');

                    $hasData = \App\Models\Gasto::activos()
                        ->whereDate('FechaEmision', '=', $fecha)
                        ->exists();

                    if (!$hasData) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sin registros')
                            ->body('No hay gastos en la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }

                    return $this->redirect(route('gastos.excel', ['fecha' => $fecha]));
                })
                ->modalHeading('Descargar Excel')
                ->modalSubmitActionLabel('Descargar'),
            Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-eye')
                ->color('danger')
                ->form([
                    DatePicker::make('fecha')
                        ->label('Fecha')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->placeholder('dd/mm/yyyy')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fecha = \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d');

                    $hasData = \App\Models\Gasto::activos()
                        ->whereDate('FechaEmision', '=', $fecha)
                        ->exists();

                    if (!$hasData) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sin registros')
                            ->body('No hay gastos en la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $url = route('gastos.pdf', ['fecha' => $fecha]);
                    $this->js("window.open('{$url}', '_blank')");
                })
                ->modalHeading('Previsualizar PDF')
                ->modalSubmitActionLabel('Ver PDF'),
        ];
    }
}
