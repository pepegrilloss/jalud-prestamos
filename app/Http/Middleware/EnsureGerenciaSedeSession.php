<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Sede;
use Symfony\Component\HttpFoundation\Response;

class EnsureGerenciaSedeSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $sedeId = session('sede_activa');
            $sede = $sedeId ? Sede::find($sedeId) : null;
            
            if (!$sede || !str_contains(strtolower($sede->Nombre), 'gerencia')) {
                return redirect('/admin/select-sede');
            }
        }

        return $next($request);
    }
}
