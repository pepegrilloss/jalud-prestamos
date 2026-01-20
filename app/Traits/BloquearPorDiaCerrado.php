<?php

namespace App\Traits;

use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;

trait BloquearPorDiaCerrado
{
    /**
     * Hook antes de guardar - valida que el día esté abierto
     */
    protected function beforeSave(): void
    {
        if (!AperturaCierreDia::estaAbierto()) {
            Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /**
     * Hook antes de eliminar - valida que el día esté abierto
     */
    protected function beforeDelete(): void
    {
        if (!AperturaCierreDia::estaAbierto()) {
            Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden eliminar registros. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
