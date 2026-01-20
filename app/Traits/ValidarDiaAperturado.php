<?php

namespace App\Traits;

use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;

trait ValidarDiaAperturado
{
    /**
     * Valida que el día esté aperturado antes de realizar operaciones
     * Excepción: Creación de usuarios (solo para administrador)
     */
    public static function validarDiaAperturado(string $accion = 'crear'): bool
    {
        // Permitir creación de usuarios siempre (solo admin puede crear)
        if (request()->is('*/users/*') || request()->is('*/user/*')) {
            return true;
        }

        if (!AperturaCierreDia::estaAbierto()) {
            Notification::make()
                ->title('Día Cerrado')
                ->body('No puedes realizar esta acción. El día de operaciones está cerrado. Contacta con administración.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    /**
     * Verifica el estado del día y lanza excepción si está cerrado
     */
    public static function verificarDiaAperturado(): void
    {
        if (!AperturaCierreDia::estaAbierto()) {
            throw new \Exception('El día de operaciones está cerrado. No se pueden realizar operaciones.');
        }
    }
}
