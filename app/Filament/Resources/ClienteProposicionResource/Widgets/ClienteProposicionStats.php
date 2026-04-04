<?php

namespace App\Filament\Resources\ClienteProposicionResource\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\Pago;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ClienteProposicionStats extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorCobrador = $user->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID ?? null;

        // 1. MIS CLIENTES ACTIVOS (clientes con créditos activos en mi zona)
        $clientesQuery = Cliente::where('Activo', true);
        if ($zonaID) {
            // Filtrar clientes que tengan proposiciones activas en la zona del promotor
            $clientesQuery->whereHas('proposiciones', function ($q) use ($zonaID) {
                $q->where('ZonaID', $zonaID)
                    ->whereHas('credito', function ($q2) {
                        $q2->where('Activo', true);
                    });
            });
        }
        $clientesActivos = $clientesQuery->distinct('ClienteID')->count('ClienteID');

        // 2. MIS PRESTAMOS ACTIVOS (créditos activos en mi zona)
        $creditosQuery = Credito::where('Activo', true)
            ->whereHas('proposicion', function ($q) use ($zonaID) {
                $q->where('FueRefinanciada', 0);
                if ($zonaID) {
                    $q->where('ZonaID', $zonaID);
                }
            });
        $prestamosActivos = $creditosQuery->count();

        $stats = [
            Stat::make('Mis Clientes Activos', $clientesActivos)
                ->description('Total de clientes activos asignados')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Mis Préstamos Activos', $prestamosActivos)
                ->description('Créditos actualmente en curso')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
        ];

        // 3. COBRADO DIARIO (solo para Promotor Cobrador)
        if ($zonaID) {
            $pagosQuery = Pago::where('Activo', true)
                ->whereDate('FechaPago', now());

            if ($zonaID) {
                $pagosQuery->whereHas('cuota.credito.proposicion', function ($q) use ($zonaID) {
                    $q->where('ZonaID', $zonaID);
                });
            }

            $cobradoDiario = $pagosQuery->sum('MontoPagado');

            $stats[] = Stat::make('Cobrado Diario', 'S/ ' . number_format($cobradoDiario, 2))
                ->value('S/ ' . number_format($cobradoDiario, 2))
                ->description('Monto total recaudado hoy')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]);
        } else {
            // Mostrar "Mi Total Prestado" solo si NO es Promotor Cobrador
            // Contar TODOS los créditos (activos e inactivos/cancelados)
            // Excluir proposiciones que SON refinanciamiento (EsRefinanciamiento = true)

            $totalPrestadoQuery = ProposicionCredito::whereHas('credito')
                ->where('EsRefinanciamiento', 0);

            $totalPrestado = $totalPrestadoQuery->sum('MontoTotal');

            $stats[] = Stat::make('Mi Total Prestado', 'S/ ' . number_format($totalPrestado, 2))
                ->description('Monto global desembolsado (activos y cancelados)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');
        }

        return $stats;
    }
}
