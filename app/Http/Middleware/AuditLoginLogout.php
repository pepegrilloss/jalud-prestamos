<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Log;

class AuditLoginLogout
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response)
    {
        // Registrar logout cuando la sesión termina
        if (auth()->check() && session()->has('_logged_in')) {
            if (!session()->has('user_id_before_request') || session('user_id_before_request') !== auth()->id()) {
                session(['user_id_before_request' => auth()->id()]);
            }
        }
    }
}
