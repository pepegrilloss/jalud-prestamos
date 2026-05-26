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

        if ($user->esAdmin() || $user->puedeVerTodasLasSedes() || $user->puedeSeleccionarSedesOperativas()) {
            $query = Sede::where('Activo', true);

            // Si SOLO tiene permiso para sedes operativas, ocultar Gerencia
            if (!$user->esAdmin() && !$user->puedeVerTodasLasSedes() && $user->puedeSeleccionarSedesOperativas()) {
                $query->where('Nombre', 'NOT LIKE', '%gerencia%');
            }

            return $query->orderBy('Nombre')->get();
        }

        // Si no es admin pero tiene una sede asignada (por si acaso el middleware no la auto-seteo)
        if ($user->SedeID) {
            return Sede::where('SedeID', $user->SedeID)->get();
        }

        return collect();
    }

    public function seleccionarSede(int $sedeId): void
    {
        $user = auth()->user();
        $sede = Sede::find($sedeId);

        if (!$sede) {
            return;
        }

        // Si la sede es Gerencia y el usuario no tiene acceso directo, forzar su sede asignada
        if (str_contains(strtolower($sede->Nombre), 'gerencia') && !($user->esAdmin() || $user->puedeVerTodasLasSedes())) {
            session(['sede_activa' => $user->SedeID]);
            $this->redirect('/admin');
            return;
        }

        session(['sede_activa' => $sedeId]);

        if (str_contains(strtolower($sede->Nombre), 'gerencia')) {
            $this->redirect('/gerencia');
            return;
        }

        $this->redirect('/admin');
    }
}
