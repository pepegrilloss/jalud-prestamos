<?php

namespace App\Observers;

use App\Models\AperturaCierreDia;
use App\Models\CalendarioNoMoroso;
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

        // Validar contra Calendario No Moroso si se intenta crear como ABIERTO
        if ($model->EstadoDia === 'ABIERTO') {
            $fechaNoMorosa = CalendarioNoMoroso::where('Fecha', $model->Fecha->toDateString())
                ->where('SedeID', $model->SedeID)
                ->where('Activo', true)
                ->first();

            if ($fechaNoMorosa) {
                Log::warning('Intento de abrir día en fecha no morosa', [
                    'Fecha' => $model->Fecha,
                    'Descripcion' => $fechaNoMorosa->Descripcion,
                    'SedeID' => $model->SedeID,
                ]);
                throw new \Exception("No se puede abrir la fecha: {$fechaNoMorosa->Descripcion}");
            }
        }
    }

    /**
     * Valida antes de actualizar un registro
     */
    public function updating(AperturaCierreDia $model): void
    {
        // Si se está cambiando a ABIERTO, validar contra Calendario No Moroso
        if ($model->isDirty('EstadoDia') && $model->EstadoDia === 'ABIERTO') {
            $fechaNoMorosa = CalendarioNoMoroso::where('Fecha', $model->Fecha->toDateString())
                ->where('SedeID', $model->SedeID)
                ->where('Activo', true)
                ->first();

            if ($fechaNoMorosa) {
                Log::warning('Intento de abrir día en fecha no morosa (update)', [
                    'Fecha' => $model->Fecha,
                    'Descripcion' => $fechaNoMorosa->Descripcion,
                    'SedeID' => $model->SedeID,
                ]);
                throw new \Exception("No se puede abrir la fecha: {$fechaNoMorosa->Descripcion}");
            }
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
