<?php

namespace App\Filament\Widgets;

use App\Models\AperturaCierreDia;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiaCerradoBannerWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return false;
        }
        return !AperturaCierreDia::withoutGlobalScope('sede')
            ->where('SedeID', Auth::user()->getEffectiveSedeId())
            ->where('EstadoDia', 'ABIERTO')
            ->exists();
    }

    protected function getStats(): array
    {
        $diaAbierto = AperturaCierreDia::getDiaAbierto();

        return [
            Stat::make('⚠️ DÍA CERRADO', 'No se pueden registrar operaciones')
                ->description($diaAbierto
                    ? "El último día abierto fue: {$diaAbierto->Fecha->format('d/m/Y')}"
                    : 'No hay ningún día abierto. Contacta a administración para abrir un día.')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('danger')
                ->extraAttributes(['class' => 'border-2 border-danger-500 bg-danger-50 dark:bg-danger-900/20']),
        ];
    }
}
