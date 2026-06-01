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
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    Forms\Components\DatePicker::make('fecha_hasta')
                        ->label('Fecha Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fechaDesde = \Carbon\Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                    $fechaHasta = \Carbon\Carbon::parse($data['fecha_hasta'])->format('Y-m-d');

                    return $this->redirect(route('compras.excel', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                    ]));
                })
                ->modalHeading('Descargar Excel')
                ->modalSubmitActionLabel('Descargar'),
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-eye')
                ->color('danger')
                ->form([
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    Forms\Components\DatePicker::make('fecha_hasta')
                        ->label('Fecha Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fechaDesde = \Carbon\Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                    $fechaHasta = \Carbon\Carbon::parse($data['fecha_hasta'])->format('Y-m-d');

                    $url = route('compras.pdf', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                    ]);
                    $this->js("window.open('{$url}', '_blank')");
                })
                ->modalHeading('Previsualizar PDF')
                ->modalSubmitActionLabel('Ver PDF'),
        ];
    }
}
