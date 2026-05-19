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
                    $filtros = $this->tableFilters;
                    $fechaVenc = $filtros['FechaVencimiento'] ?? [];
                    $fechaDesde = $fechaVenc['fecha_desde'] ?? null;
                    $fechaHasta = $fechaVenc['fecha_hasta'] ?? null;
                    $sedeId = $filtros['SedeID']['value'] ?? null;
                    $clienteId = $filtros['cliente']['value'] ?? null;
                    $tipoCreditoId = $filtros['tipoCredito']['value'] ?? null;

                    if (!$fechaDesde && !$fechaHasta) {
                        $fechaDesde = now()->toDateString();
                        $fechaHasta = now()->toDateString();
                    }

                    $params = [];
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;
                    if ($sedeId) $params['sede_id'] = $sedeId;
                    if ($clienteId) $params['cliente_id'] = $clienteId;
                    if ($tipoCreditoId) $params['tipo_credito_id'] = $tipoCreditoId;

                    $url = route('creditos-vencidos.view', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
