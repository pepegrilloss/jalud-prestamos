<?php

namespace App\Filament\Resources\ClienteProposicionResource\Widgets;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ClienteProposicionStats extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorID = $user->PromotorCobradorID ?? null;

        // 1. MIS CLIENTES ACTIVOS
        $clientesQuery = Cliente::where('Activo', true);
        if ($promotorID) {
            $clientesQuery->where('PromotorCobradorID', $promotorID);
        }
        $clientesActivos = $clientesQuery->count();

        // 2. MIS PRESTAMOS ACTIVOS
        // Se asume que un préstamo activo es un Crédito con Activo = true
        $creditosQuery = Credito::where('Activo', true);
        if ($promotorID) {
            $creditosQuery->whereHas('proposicion.cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }
        $prestamosActivos = $creditosQuery->count();

        // 3. MI TOTAL PRESTADO
        // Suma del MontoTotal de las proposiciones asociadas a créditos activos
        $totalPrestadoQuery = ProposicionCredito::whereHas('credito', function ($q) {
            $q->where('Activo', true);
        });
        if ($promotorID) {
            $totalPrestadoQuery->whereHas('cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }
        $totalPrestado = $totalPrestadoQuery->sum('MontoTotal');

        return [
            Stat::make('Mis Clientes Activos', $clientesActivos)
                ->description('Total de clientes activos asignados')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Mis Préstamos Activos', $prestamosActivos)
                ->description('Créditos actualmente en curso')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Mi Total Prestado', 'S/ ' . number_format($totalPrestado, 2))
                ->description('Monto global desembolsado activo')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
