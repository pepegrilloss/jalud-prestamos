<?php

namespace App\Filament\Resources\ReporteClientesAtrasoResource\Pages;

use App\Filament\Resources\ReporteClientesAtrasoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReporteClientesAtraso extends ListRecords
{
    protected static string $resource = ReporteClientesAtrasoResource::class;

    public function getTitle(): string
    {
        return 'Clientes con Días de Atraso';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $filtros = $this->tableFilters;
                    $params = [];

                    $cliente = $filtros['cliente']['value'] ?? null;
                    if ($cliente) $params['cliente_id'] = $cliente;

                    $fechaDesde = $filtros['fecha']['fecha_desde'] ?? null;
                    $fechaHasta = $filtros['fecha']['fecha_hasta'] ?? null;
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;

                    $url = route('clientes-atraso.view', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),

            Actions\Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    $filtros = $this->tableFilters;
                    $params = [];

                    $cliente = $filtros['cliente']['value'] ?? null;
                    if ($cliente) $params['cliente_id'] = $cliente;

                    $fechaDesde = $filtros['fecha']['fecha_desde'] ?? null;
                    $fechaHasta = $filtros['fecha']['fecha_hasta'] ?? null;
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;

                    $url = route('reporte-atraso.excel', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
