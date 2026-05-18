<?php

namespace App\Filament\Widgets;

use App\Models\FondoSede;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CajaAbiertaWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $sedeId = $user->SedeID;

        if ($user->isPrivileged()) {
            $sedeActiva = session('sede_activa');
            if ($sedeActiva) {
                $sedeId = $sedeActiva;
            }
        }

        if ($sedeId) {
            $fondo = FondoSede::where('SedeID', $sedeId)->first();
            $saldoCajaAbierta = $fondo ? (float) $fondo->Saldo : 0;
            $sedeNombre = $fondo?->sede?->Nombre ?? 'Sede actual';
            $ultimaActualizacion = $fondo?->updated_at?->diffForHumans() ?? 'Sin movimientos';

            $color = match(true) {
                $saldoCajaAbierta <= 0 => 'danger',
                $saldoCajaAbierta < 500 => 'danger',
                $saldoCajaAbierta < 2000 => 'warning',
                default => 'success',
            };

            $alerta = $saldoCajaAbierta <= 0 ? '⚠️ ¡CAJA VACÍA!' : ($saldoCajaAbierta < 500 ? '⚠️ Saldo crítico' : '');

            return [
                Stat::make("Caja Abierta - {$sedeNombre}", 'S/ ' . number_format($saldoCajaAbierta, 2))
                    ->description($alerta ?: "Último movimiento: {$ultimaActualizacion}")
                    ->descriptionIcon($alerta ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-building-storefront')
                    ->color($color),
            ];
        }

        return [
            Stat::make('Caja Abierta', 'S/ 0.00')
                ->description('Sin sede asignada')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('gray'),
        ];
    }
}
