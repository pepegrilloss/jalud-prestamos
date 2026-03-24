<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Filament\Facades\Filament;

class EnsureSedeIsSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar si el usuario está autenticado en el panel de admin
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $path = $request->path();

        // Excluir rutas que no deben ser redirigidas (login, logout, selección de sede, etc.)
        $excludedPaths = [
            'admin/login',
            'admin/logout',
            'admin/select-sede',
            'livewire/message/select-sede', // Permitir las llamadas de livewire para esta página
        ];

        foreach ($excludedPaths as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return $next($request);
            }
        }

        // Si ya tiene una sede seleccionada en la sesión, continuar
        if (session()->exists('sede_activa')) {
            return $next($request);
        }

        // --- Lógica de auto-selección ---

        // Si el usuario NO es admin y tiene una sede fija asignada, la seteamos automáticamente
        if (!$user->esAdmin() && $user->SedeID) {
            session(['sede_activa' => $user->SedeID]);
            return $next($request);
        }

        // Si es admin, lo obligamos a pasar por la pantalla de selección
        if ($user->esAdmin()) {
            return redirect('/admin/select-sede');
        }

        // Por defecto, si llegamos aquí y no hay sede, enviamos a seleccionar
        return redirect('/admin/select-sede');
    }
}
