<?php

namespace App\Traits;

use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;

trait BloquearPorDiaCerrado
{
    /**
     * Hook antes de guardar - valida que el registro no esté cerrado (FechaCierre)
     * No valida si hoy está abierto, porque un registro reabierto puede editarse aunque hoy esté cerrado
     */
    protected function beforeSave(): void
    {
        // Si el registro tiene FechaCierre, está cerrado y no se puede editar
        if ($this->record && $this->record->FechaCierre !== null) {
            Notification::make()
                ->title('❌ Registro Cerrado')
                ->body('Este registro está cerrado y no puede ser modificado. Debe reabrir la fecha para continuar.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /**
     * Hook antes de eliminar - valida que el registro no esté cerrado
     * No valida si hoy está abierto, porque un registro reabierto puede eliminarse aunque hoy esté cerrado
     */
    protected function beforeDelete(): void
    {
        // Si el registro tiene FechaCierre, está cerrado y no se puede eliminar
        if ($this->record && $this->record->FechaCierre !== null) {
            Notification::make()
                ->title('❌ Registro Cerrado')
                ->body('Este registro está cerrado y no puede ser eliminado. Debe reabrir la fecha para continuar.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}

