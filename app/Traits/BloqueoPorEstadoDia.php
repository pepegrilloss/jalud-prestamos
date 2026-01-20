<?php

namespace App\Traits;

use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;

trait BloqueoPorEstadoDia
{
    /**
     * Valida el estado del día antes de crear
     * Usado en Filament Resources
     */
    protected function validarDiaAbiertoPara(string $accion = 'crear'): bool
    {
        // Permitir siempre acceso a usuarios (será validado por policy)
        if ($this->getModel() && class_basename($this->getModel()) === 'User') {
            return true;
        }

        // Permitir siempre acceso a apertura/cierre (solo admin)
        if ($this->getModel() && class_basename($this->getModel()) === 'AperturaCierreDia') {
            return true;
        }

        if (!AperturaCierreDia::estaAbierto()) {
            Notification::make()
                ->title('Día Cerrado')
                ->body("No puedes $accion registros. El día de operaciones está cerrado. Contacta con administración.")
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        return true;
    }

    /**
     * Verificar estado del día (lanza excepción)
     */
    public function verificarDiaAbierto(): void
    {
        if (!AperturaCierreDia::estaAbierto()) {
            throw new \Exception('El día de operaciones está cerrado. No se pueden realizar operaciones.');
        }
    }
}
