<?php

namespace App\Filament\Widgets;

use App\Models\Cuota;
use App\Models\Pago;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CobranzaStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = Auth::user();
        $promotorID = $user->PromotorCobradorID ?? null;

        // 1. Cobrado Diario (Suma de montos)
        $pagosQuery = Pago::where('Activo', true)
            ->whereDate('FechaPago', now());

        if ($promotorID) {
            $pagosQuery->where('PromotorCobradorID', $promotorID);
        }

        $cobradoDiario = $pagosQuery->sum('MontoPagado');
        $pagosCount = $pagosQuery->count();

        // 2. Créditos que vencen hoy (última cuota de cada crédito con vencimiento hoy, sin domingo ni feriado)
        // Obtener todos los créditos activos no refinanciados
        $creditosConUltimaCuota = Cuota::where('Activo', true)
            ->where('Estado', '!=', 'PAGADA')
            ->where('Estado', '!=', 'DOMINGO')
            ->where('Estado', '!=', 'FERIADO')
            ->whereHas('credito.proposicion', function ($q) {
                $q->where('FueRefinanciada', 0);
            });

        if ($promotorID) {
            $creditosConUltimaCuota->whereHas('credito.proposicion.cliente', function ($q) use ($promotorID) {
                $q->where('PromotorCobradorID', $promotorID);
            });
        }

        // Agrupar por CreditoID y obtener la cuota con mayor FechaVencimiento
        $cuotasUltimas = $creditosConUltimaCuota->selectRaw('CreditoID, MAX(FechaVencimiento) as FechaVencimiento')
            ->groupBy('CreditoID')
            ->get();

        // Contar cuántas de esas cuotas tienen FechaVencimiento = hoy
        $vencenHoy = 0;
        foreach ($cuotasUltimas as $cuota) {
            if ($cuota->FechaVencimiento && \Carbon\Carbon::parse($cuota->FechaVencimiento)->isToday()) {
                $vencenHoy++;
            }
        }

        return [
            Stat::make('Cobrado Diario', 'S/ ' . number_format($cobradoDiario, 2))
                ->value('S/ ' . number_format($cobradoDiario, 2))
                ->description('Monto total recaudado hoy')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Créditos Vencen Hoy', $vencenHoy)
                ->description('Cuotas con vencimiento hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Pagos Cerrados Hoy', $pagosCount)
                ->description('Transacciones registradas hoy')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('primary'),
        ];
    }
}
