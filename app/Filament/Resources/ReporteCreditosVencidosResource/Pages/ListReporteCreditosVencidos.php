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
                ->color('danger')
                ->action(function () {
                    $filtros = $this->tableFilters['FechaVencimiento'] ?? [];
                    $fechaDesde = $filtros['fecha_desde'] ?? null;
                    $fechaHasta = $filtros['fecha_hasta'] ?? null;

                    if (!$fechaDesde && !$fechaHasta) {
                        $fechaDesde = now()->toDateString();
                        $fechaHasta = now()->toDateString();
                    }

                    $params = [];
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;

                    $url = route('creditos-vencidos.view', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
