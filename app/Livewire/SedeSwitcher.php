<?php

namespace App\Livewire;

use App\Models\Sede;
use Livewire\Component;

class SedeSwitcher extends Component
{
    public ?int $sedeActiva = null;
    public string $sedeNombre = 'Todas las Sedes';

    public function mount(): void
    {
        $this->sedeActiva = session('sede_activa');
        $this->actualizarNombre();
    }

    public function cambiarSede(?int $sedeId): void
    {
        if ($sedeId === 0) {
            $sedeId = null;
        }

        session(['sede_activa' => $sedeId]);
        $this->sedeActiva = $sedeId;
        $this->actualizarNombre();

        // Forzar recarga completa de la página para que Filament y todos los datos 
        // tomen el nuevo Global Scope y el topbar se limpie correctamente.
        $this->redirect(request()->header('Referer', '/admin'));
    }

    private function actualizarNombre(): void
    {
        if ($this->sedeActiva) {
            $sede = Sede::withoutGlobalScopes()->find($this->sedeActiva);
            $this->sedeNombre = $sede?->Nombre ?? 'Sede desconocida';
        } else {
            $this->sedeNombre = 'Todas las Sedes';
        }
    }

    public function render()
    {
        $sedes = Sede::withoutGlobalScopes()
            ->where('Activo', true)
            ->orderBy('Nombre')
            ->get();

        return view('livewire.sede-switcher', [
            'sedes' => $sedes,
        ]);
    }
}
