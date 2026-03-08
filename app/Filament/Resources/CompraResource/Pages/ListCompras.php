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
        if (!AperturaCierreDia::estaAbierto()) {
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
                ->color('danger')
                ->form([
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\DatePicker::make('fecha_hasta')
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
                    
                    return $this->redirect(route('compras.excel', $params));
                })
                ->modalHeading('Descargar Excel')
                ->modalSubmitActionLabel('Descargar'),
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->form([
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Fecha Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\DatePicker::make('fecha_hasta')
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
                    
                    return $this->redirect(route('compras.pdf', $params));
                })
                ->modalHeading('Descargar PDF')
                ->modalSubmitActionLabel('Descargar'),
        ];
    }
}


