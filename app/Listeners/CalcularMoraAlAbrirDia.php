<?php

namespace App\Listeners;

use App\Jobs\CalcularMoraAutomatica;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * CalcularMoraAlAbrirDia
 *
 * Se ejecuta automáticamente cuando se abre un nuevo día en el sistema.
 * Calcula la mora para todos los créditos vencidos.
 */
class CalcularMoraAlAbrirDia implements ShouldQueueAfterCommit
{
    public $connection = 'background';

    public function handle($event)
    {
        set_time_limit(300);

        Log::info('[LISTENER] CalcularMoraAlAbrirDia disparado', [
            'event' => get_class($event),
            'fecha' => $event?->aperturaCierre?->Fecha ?? 'desconocida',
        ]);

        // El listener corre en segundo plano, después de confirmar la apertura.
        try {
            $fecha = $event->aperturaCierre?->Fecha;
            Log::info('[LISTENER] Despachando CalcularMoraAutomatica sincronamente para: '.$fecha);
            // El trabajo pesado se ejecuta después de enviar la respuesta al usuario.
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
