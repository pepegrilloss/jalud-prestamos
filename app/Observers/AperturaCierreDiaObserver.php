<?php

namespace App\Observers;

use App\Models\AperturaCierreDia;
use Illuminate\Support\Facades\Log;

class AperturaCierreDiaObserver
{
    /**
     * Ejecuta acciones después de actualizar apertura/cierre
     */
    public function updated(AperturaCierreDia $model): void
    {
        Log::info('AperturaCierreDiaObserver.updated disparado', [
            'EstadoDia' => $model->EstadoDia,
            'wasChanged' => $model->wasChanged('EstadoDia'),
            'Fecha' => $model->Fecha,
        ]);

        // Si cambió a CERRADO, ejecutar cierre
        if ($model->wasChanged('EstadoDia') && $model->EstadoDia === 'CERRADO') {
            Log::info('Iniciando cierre de día', ['Fecha' => $model->Fecha]);
            try {
                $model->cerrarDia();
                Log::info('Cierre de día completado exitosamente', ['Fecha' => $model->Fecha]);
            } catch (\Exception $e) {
                Log::error('Error en cerrarDia()', [
                    'error' => $e->getMessage(),
                    'Fecha' => $model->Fecha,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Ejecuta acciones después de crear
     */
    public function created(AperturaCierreDia $model): void
    {
        // Si se crea como ABIERTO, establecer fecha de apertura
        if ($model->EstadoDia === 'ABIERTO' && !$model->FechaApertura) {
            $model->update([
                'FechaApertura' => now(),
                'UsuarioAperturaID' => auth()->id(),
            ]);
        }
    }
}
