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
        // El acceso de reportes de Gerencia ahora se realiza desde el modal Balance diario.
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->esAdmin() || $user->puedeVerTodasLasSedes());
    }
}
