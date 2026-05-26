<?php

namespace App\Filament\Pages;

use App\Models\Sede;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class GerenciaReportes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $title = 'Reportes de Gestión';
    protected static string $view = 'filament.pages.gerencia-reportes';
    protected static ?int $navigationSort = 1;

    protected function getViewData(): array
    {
        return [
            'sedes' => Sede::where('Activo', true)
                ->orderBy('Nombre')
                ->pluck('Nombre', 'SedeID'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'gerencia';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
