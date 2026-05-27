<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;

class ListPagos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PagoResource::class;

    public function getTitle(): string
    {
        $title = 'Pagos';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $bloqueado = auth()->user()?->hasRole('promotor_cobrador')
            && \Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
                ->where('pagos_promotor_bloqueados', 1)
                ->where('SedeID', auth()->user()->SedeID)
                ->exists();

        if (AperturaCierreDia::estaAbierto() && !$bloqueado) {
            $actions[] = Actions\CreateAction::make()
                ->label('Registrar Pago')
                ->visible(function () use ($bloqueado) {
                    if (auth()->user()?->hasRole('promotor_cobrador')) {
                        $aunBloqueado = \Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
                            ->where('pagos_promotor_bloqueados', 1)
                            ->where('SedeID', auth()->user()->SedeID)
                            ->exists();
                        return !$aunBloqueado;
                    }
                    return true;
                });
        }

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\PagosCantidadStatsWidget::class,
            \App\Filament\Widgets\PagosMontoStatsWidget::class,
            \App\Filament\Widgets\PagosMontoMesStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 3,
            'lg' => 3,
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            $promotorCobrador = auth()->user()->promotorCobrador;
            
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                return $query->whereHas('cuota.credito.proposicion', function (Builder $q) use ($promotorCobrador) {
                    $q->where('ZonaID', $promotorCobrador->ZonaID);
                });
            }

            return $query->whereRaw('1 = 0');
        }

        // Si NO tiene el permiso "ver_todos_los_pagos", filtrar SOLO pagos del día abierto
        if (!auth()->user()?->can('ver_todos_los_pagos')) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $query->whereDate('pago.FechaPago', $fechaAbierta->toDateString());
            }
        }

        return $query;
    }
    
}