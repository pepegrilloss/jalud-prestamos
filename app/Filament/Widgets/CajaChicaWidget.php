<?php

namespace App\Filament\Widgets;

use App\Models\FondoSede;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CajaChicaWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 11;
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

        $sedeId = null;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            $filter = session('gerencia_dashboard_sede', '0');
            if ($filter !== '0' && $filter !== '' && $filter !== null) {
                $sedeId = (int) $filter;
            }
        }
        if (!$sedeId) {
            $sedeId = $user->SedeID;
            if ($user->isPrivileged()) {
                $sedeActiva = session('sede_activa');
                if ($sedeActiva) {
                    $sedeId = $sedeActiva;
                }
            }
        }

        if ($sedeId) {
            $fondo = FondoSede::withoutGlobalScope('sede')->where('SedeID', $sedeId)->first();
            $saldoCajaChica = $fondo ? $fondo->SaldoCajaChica : 0;
            $sedeNombre = $fondo?->sede?->Nombre ?? 'Sede actual';

            return [
                Stat::make("Caja Chica - {$sedeNombre}", 'S/ ' . number_format($saldoCajaChica, 2))
                    ->description('Para gastos operativos')
                    ->descriptionIcon('heroicon-m-wallet')
                    ->color($saldoCajaChica > 0 ? 'info' : 'gray'),
            ];
        }

        return [
            Stat::make('Caja Chica', 'S/ 0.00')
                ->description('Sin sede asignada')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('gray'),
        ];
    }
}
