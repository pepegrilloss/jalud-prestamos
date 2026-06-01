<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Models\AperturaCierreDia;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;

class ListCompras extends ListRecords
{
    protected static string $resource = CompraResource::class;

    public function getTitle(): string
    {
        $title = 'Compras';
        if (filament()->getCurrentPanel()?->getId() !== 'gerencia' && !AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
            Actions\Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->placeholder('dd/mm/yyyy')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fecha = \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d');

                    $hasData = \App\Models\Compra::activos()
                        ->whereDate('FechaEmision', '=', $fecha)
                        ->exists();

                    if (!$hasData) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sin registros')
                            ->body('No hay compras en la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }

                    return $this->redirect(route('compras.excel', ['fecha' => $fecha]));
                })
                ->modalHeading('Descargar Excel')
                ->modalSubmitActionLabel('Descargar'),
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-eye')
                ->color('danger')
                ->form([
                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->placeholder('dd/mm/yyyy')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fecha = \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d');

                    $hasData = \App\Models\Compra::activos()
                        ->whereDate('FechaEmision', '=', $fecha)
                        ->exists();

                    if (!$hasData) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sin registros')
                            ->body('No hay compras en la fecha seleccionada.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $url = route('compras.pdf', ['fecha' => $fecha]);
                    $this->js("window.open('{$url}', '_blank')");
                })
                ->modalHeading('Previsualizar PDF')
                ->modalSubmitActionLabel('Ver PDF'),
        ];
    }
}
