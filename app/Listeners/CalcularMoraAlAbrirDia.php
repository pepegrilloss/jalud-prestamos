<?php

namespace App\Listeners;

use App\Jobs\CalcularMoraAutomatica;
use App\Models\AperturaCierreDia;
use Illuminate\Support\Facades\Log;

/**
 * CalcularMoraAlAbrirDia
 * 
 * Se ejecuta automáticamente cuando se abre un nuevo día en el sistema.
 * Calcula la mora para todos los créditos vencidos.
 */
class CalcularMoraAlAbrirDia
{
    public function handle($event)
    {
        Log::info('[LISTENER] CalcularMoraAlAbrirDia disparado', [
            'event' => get_class($event),
            'fecha' => $event?->aperturaCierre?->Fecha ?? 'desconocida',
        ]);
        
        // Ejecutar el Job sincronamente (no en cola) para calcular mora inmediatamente
        try {
            $fecha = $event->aperturaCierre?->Fecha;
            Log::info('[LISTENER] Despachando CalcularMoraAutomatica sincronamente para: ' . $fecha);
            // Usar dispatchSync para ejecutar INMEDIATAMENTE sin cola
            CalcularMoraAutomatica::dispatchSync($fecha);
            Log::info('[LISTENER] CalcularMoraAutomatica ejecutado correctamente');
        } catch (\Exception $e) {
            Log::error('[LISTENER] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
