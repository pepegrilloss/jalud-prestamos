<?php

namespace App\Observers;

use App\Models\AperturaCierreDia;
use App\Events\DiaAbierto;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AperturaCierreDiaObserver
{
    /**
     * Valida antes de crear un registro
     */
    public function creating(AperturaCierreDia $model): void
    {
        // Validar que la fecha no sea futura
        $fechaIngresada = Carbon::parse($model->Fecha)->startOfDay();
        $hoy = today();
        
        if ($fechaIngresada->isAfter($hoy)) {
            Log::warning('Intento de crear apertura/cierre con fecha futura', [
                'Fecha' => $model->Fecha,
                'Hoy' => $hoy,
                'Usuario' => auth()->user()?->name ?? 'desconocido'
            ]);
            throw new \Exception('No se puede crear períodos de apertura/cierre con fechas futuras. Solo se permite la fecha de hoy o anteriores.');
        }
    }

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

        // Si cambió a ABIERTO, disparar evento para calcular mora
        if ($model->wasChanged('EstadoDia') && $model->EstadoDia === 'ABIERTO') {
            Log::info('Día abierto - Disparando evento DiaAbierto', ['Fecha' => $model->Fecha]);
            DiaAbierto::dispatch($model);
        }

        // Si cambió a CERRADO, ejecutar cierre (como respaldo si no se hizo en la transacción del Resource)
        if ($model->wasChanged('EstadoDia') && $model->EstadoDia === 'CERRADO') {
            Log::info('Iniciando cierre de día (Observer)', ['Fecha' => $model->Fecha]);
            try {
                $model->cerrarDia();
                Log::info('Cierre de día completado exitosamente (Observer)', ['Fecha' => $model->Fecha]);
            } catch (\Exception $e) {
                Log::error('Error en cerrarDia() - Revirtiendo EstadoDia a ABIERTO', [
                    'error' => $e->getMessage(),
                    'Fecha' => $model->Fecha,
                    'SedeID' => $model->SedeID,
                ]);
                // Revertir el estado para que el día no quede cerrado a medias
                $model->updateQuietly([
                    'EstadoDia' => 'ABIERTO',
                    'FechaCierre' => null,
                    'UsuarioCierreID' => null,
                ]);
            }
        }
    }

    /**
     * Ejecuta acciones después de crear
     */
    public function created(AperturaCierreDia $model): void
    {
        // Si se crea como ABIERTO, disparar evento para calcular mora
        if ($model->EstadoDia === 'ABIERTO') {
            // Si no tiene FechaApertura, asignarla
            if (!$model->FechaApertura) {
                $model->update([
                    'FechaApertura' => now(),
                    'UsuarioAperturaID' => auth()->id(),
                ]);
            }
            
            Log::info('Día creado y abierto - Disparando evento DiaAbierto', ['Fecha' => $model->Fecha]);
            DiaAbierto::dispatch($model);
        }
    }
}
