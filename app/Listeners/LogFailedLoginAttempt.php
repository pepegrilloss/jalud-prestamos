<?php

namespace App\Listeners;

use App\Models\Log as AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\RateLimiter;

class LogFailedLoginAttempt
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $ip = request()->ip();
        $key = 'login_attempts:' . $ip;
        
        // Incrementar el contador de intentos fallidos
        RateLimiter::hit($key, $minutes = 15);
        
        // Obtener el número de intentos
        $attempts = RateLimiter::attempts($key);
        $identificador = $event->credentials['username'] ?? $event->credentials['email'] ?? 'desconocido';
        $usuario = User::query()
            ->where('username', $identificador)
            ->orWhere('email', $identificador)
            ->first();

        AuditLog::registrar(
            'LOGIN_FALLIDO',
            'Auth',
            $usuario?->id,
            null,
            [
                'identificador' => $identificador,
                'intentos_desde_ip' => $attempts,
            ],
            $usuario?->SedeID
        );
        
        // Log del intento fallido
        \Log::warning('SEGURIDAD - INTENTO DE LOGIN FALLIDO', [
            'Usuario' => $identificador,
            'IP' => $ip,
            'Intentos' => $attempts,
            'UserAgent' => request()->userAgent(),
            'Timestamp' => now()
        ]);
        
        // Alerta si hay muchos intentos
        if ($attempts >= 3) {
            \Log::alert('SEGURIDAD - MÚLTIPLES INTENTOS DE LOGIN FALLIDOS', [
                'IP' => $ip,
                'Intentos' => $attempts,
                'LímitiPermitido' => 5
            ]);
        }
    }
}
