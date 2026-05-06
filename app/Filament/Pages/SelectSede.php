<?php

namespace App\Filament\Pages;

use App\Models\Sede;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SelectSede extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static string $view = 'filament.pages.select-sede';
    protected static ?string $title = 'Seleccionar Sede de Trabajo';
    protected static bool $shouldRegisterNavigation = false;

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.base';
    }

    public function getSedes(): Collection
    {
        $user = auth()->user();

        if ($user->esAdmin()) {
            return Sede::where('Activo', true)
                ->orderBy('Nombre')
                ->get();
        }

        // Si no es admin pero tiene una sede asignada (por si acaso el middleware no la auto-seteo)
        if ($user->SedeID) {
            return Sede::where('SedeID', $user->SedeID)->get();
        }

        return collect();
    }

    public function seleccionarSede(?int $sedeId = null): void
    {
        // sedeId null significa "Todas las Sedes" (solo para admins)
        if ($sedeId === 0) {
            $sedeId = null;
        }

        session(['sede_activa' => $sedeId]);
        
        // Verificar si la sede seleccionada es "Gerencia General"
        if ($sedeId) {
            $sede = Sede::find($sedeId);
            if ($sede && str_contains(strtolower($sede->Nombre), 'gerencia')) {
                $this->redirect('/gerencia');
                return;
            }
        }
        
        $this->redirect('/admin');
    }
}
