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

        if ($user->esAdmin()) {
            $sedeActiva = session('sede_activa');
            if ($sedeActiva) {
                $sedeId = $sedeActiva;
            }
        }

        if ($sedeId) {
            $fondo = FondoSede::where('SedeID', $sedeId)->first();
            $saldoCajaAbierta = $fondo ? $fondo->Saldo : 0;
            $sedeNombre = $fondo?->sede?->Nombre ?? 'Sede actual';
            $ultimaActualizacion = $fondo?->updated_at?->diffForHumans() ?? 'Sin movimientos';

            return [
                Stat::make("Caja Abierta - {$sedeNombre}", 'S/ ' . number_format($saldoCajaAbierta, 2))
                    ->description("Último movimiento: {$ultimaActualizacion}")
                    ->descriptionIcon('heroicon-m-building-storefront')
                    ->color($saldoCajaAbierta > 0 ? 'success' : 'danger'),
            ];
        }

        $totalCajaAbierta = FondoSede::sum('Saldo');
        $sedeCount = FondoSede::count();

        return [
            Stat::make('Caja Abierta - Total General', 'S/ ' . number_format($totalCajaAbierta, 2))
                ->description("{$sedeCount} sedes con fondos")
                ->descriptionIcon('heroicon-m-building-library')
                ->color('success'),
        ];
    }
}
