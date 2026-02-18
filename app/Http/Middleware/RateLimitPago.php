<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPago
{
    /**
     * SEGURIDAD: Rate Limiting para creación de pagos
     * 
     * Previene abuso: máximo 10 pagos por usuario en 1 hora
     * Esto evita ataques de fuerza bruta o manipulación masiva
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar a rutas de creación de pagos
        if ($request->isMethod('post') && ($request->is('*/pago*') || $request->routeIs('filament.admin.resources.pagos.create'))) {
            $userID = auth()->id() ?? $request->ip();
            $key = "pago_creation:{$userID}";
            
            // Máximo 10 pagos por hora por usuario
            $maxAttempts = 10;
            $decayMinutes = 60;
            
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($key);
                
                // SEGURIDAD: Log del intento bloqueado
                \Log::warning('SEGURIDAD - RATE LIMIT: Demasiados intentos de creación de pago', [
                    'UserID' => auth()->id(),
                    'IP' => $request->ip(),
                    'Timestamp' => now(),
                    'RemainingSeconds' => $retryAfter
                ]);
                
                return response()->json([
                    'message' => 'Demasiados intentos de creación de pagos. Por favor intenta en ' . ceil($retryAfter / 60) . ' minutos.'
                ], 429);
            }
            
            RateLimiter::hit($key, $decayMinutes * 60);
        }

        return $next($request);
    }
}
