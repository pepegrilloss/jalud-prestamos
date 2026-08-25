<?php

namespace App\Filament\Resources\ReporteProyeccionResource\Pages;

use App\Filament\Resources\ReporteProyeccionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListReporteProyeccion extends ListRecords
{
    protected static string $resource = ReporteProyeccionResource::class;

    public function getTitle(): string
    {
        return 'Reporte Proyección';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->form([
                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Desde (Fecha de Vencimiento)')
                        ->default(now()->startOfMonth())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    Forms\Components\DatePicker::make('fecha_hasta')
                        ->label('Hasta (Fecha de Vencimiento)')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $fechaDesde = is_string($data['fecha_desde'] ?? null)
                        ? $data['fecha_desde']
                        : ($data['fecha_desde'] ?? now())->format('Y-m-d');
                    $fechaHasta = is_string($data['fecha_hasta'] ?? null)
                        ? $data['fecha_hasta']
                        : ($data['fecha_hasta'] ?? now())->format('Y-m-d');

                    $url = route('reporte-proyeccion.excel', [
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                    ]);

                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
