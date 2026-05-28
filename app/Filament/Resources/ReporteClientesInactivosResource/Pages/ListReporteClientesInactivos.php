<?php

namespace App\Filament\Resources\ReporteClientesInactivosResource\Pages;

use App\Filament\Resources\ReporteClientesInactivosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReporteClientesInactivos extends ListRecords
{
    protected static string $resource = ReporteClientesInactivosResource::class;

    public function getTitle(): string
    {
        return 'Clientes Inactivos';
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

                    $nombre = $filtros['cliente_filtro']['nombre'] ?? null;
                    if ($nombre) $params['nombre'] = $nombre;

                    $fechaDesde = $filtros['fecha']['fecha_desde'] ?? null;
                    $fechaHasta = $filtros['fecha']['fecha_hasta'] ?? null;
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;

                    $url = route('clientes-inactivos.view', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),

            Actions\Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    $filtros = $this->tableFilters;
                    $params = [];

                    $nombre = $filtros['cliente_filtro']['nombre'] ?? null;
                    if ($nombre) $params['nombre'] = $nombre;

                    $fechaDesde = $filtros['fecha']['fecha_desde'] ?? null;
                    $fechaHasta = $filtros['fecha']['fecha_hasta'] ?? null;
                    if ($fechaDesde) $params['fecha_desde'] = $fechaDesde;
                    if ($fechaHasta) $params['fecha_hasta'] = $fechaHasta;

                    $url = route('reporte-inactivos.excel', $params);
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
