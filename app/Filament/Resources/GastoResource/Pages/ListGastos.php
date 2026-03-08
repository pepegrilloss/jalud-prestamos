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
        if (!AperturaCierreDia::estaAbierto()) {
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
                ->color('danger')
                ->form([
                    DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('fecha_hasta')
                        ->label('Fecha Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data) {
                    $params = [];
                    
                    if (!empty($data['fecha_desde'])) {
                        $fechaDesde = is_object($data['fecha_desde']) 
                            ? $data['fecha_desde']->format('Y-m-d')
                            : \Carbon\Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                        $params['fecha_desde'] = $fechaDesde;
                    }
                    
                    if (!empty($data['fecha_hasta'])) {
                        $fechaHasta = is_object($data['fecha_hasta']) 
                            ? $data['fecha_hasta']->format('Y-m-d')
                            : \Carbon\Carbon::parse($data['fecha_hasta'])->format('Y-m-d');
                        $params['fecha_hasta'] = $fechaHasta;
                    }
                    
                    return $this->redirect(route('gastos.excel', $params));
                })
                ->modalHeading('Descargar Excel')
                ->modalSubmitActionLabel('Descargar'),
            Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->form([
                    DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('fecha_hasta')
                        ->label('Fecha Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data) {
                    $params = [];
                    
                    if (!empty($data['fecha_desde'])) {
                        $fechaDesde = is_object($data['fecha_desde']) 
                            ? $data['fecha_desde']->format('Y-m-d')
                            : \Carbon\Carbon::parse($data['fecha_desde'])->format('Y-m-d');
                        $params['fecha_desde'] = $fechaDesde;
                    }
                    
                    if (!empty($data['fecha_hasta'])) {
                        $fechaHasta = is_object($data['fecha_hasta']) 
                            ? $data['fecha_hasta']->format('Y-m-d')
                            : \Carbon\Carbon::parse($data['fecha_hasta'])->format('Y-m-d');
                        $params['fecha_hasta'] = $fechaHasta;
                    }
                    
                    return $this->redirect(route('gastos.pdf', $params));
                })
                ->modalHeading('Descargar PDF')
                ->modalSubmitActionLabel('Descargar'),
        ];
    }
}
