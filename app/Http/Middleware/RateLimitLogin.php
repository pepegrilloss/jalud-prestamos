<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class RateLimitLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Solo aplicar rate limiting a intentos fallidos de login
        if ($request->isMethod('post') && $request->is('*/login')) {
            $key = 'login_attempts:' . $request->ip();
            
            // 5 intentos por 15 minutos (900 segundos)
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $retryAfter = RateLimiter::availableIn($key);
                
                // Log del intento bloqueado
                \Log::warning('SEGURIDAD - RATE LIMIT: Múltiples intentos de login fallidos', [
                    'IP' => $request->ip(),
                    'UserAgent' => $request->userAgent(),
                    'Timestamp' => now(),
                    'RemainingSeconds' => $retryAfter
                ]);
                
                return response()->json([
                    'message' => 'Demasiados intentos de inicio de sesión. Por favor intente más tarde.'
                ], 429);
            }
        }

        return $next($request);
    }
}
