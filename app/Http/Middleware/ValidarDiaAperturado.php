<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\Response;

class ValidarDiaAperturado
{
    /**
     * Valida que el día esté abierto para operaciones
     * Excepciones: Autenticación, Usuarios, Apertura/Cierre, Lecturas
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Permitir SIEMPRE rutas de autenticación
        if ($this->esRutaAutenticacion($request)) {
            return $next($request);
        }

        // Permitir SIEMPRE lecturas (GET, HEAD, OPTIONS)
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Permitir SIEMPRE gestión de usuarios
        if ($this->esGestionUsuarios($request)) {
            return $next($request);
        }

        // Permitir SIEMPRE gestión de apertura/cierre
        if ($this->esGestionAperturaCierre($request)) {
            return $next($request);
        }

        // Para cualquier otra operación POST/PUT/PATCH/DELETE, validar día abierto
        if (!AperturaCierreDia::estaAbierto()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'El día de operaciones está cerrado. No se pueden realizar operaciones.',
                    'estado' => AperturaCierreDia::estadoDiaActual(),
                ], 403);
            }

            Notification::make()
                ->title('Día Cerrado')
                ->body('No puedes realizar esta acción. El día de operaciones está cerrado. Contacta con administración.')
                ->danger()
                ->send();

            return back();
        }

        return $next($request);
    }

    /**
     * Rutas de autenticación - PERMITIR SIEMPRE
     */
    private function esRutaAutenticacion(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_contains($path, '/login') ||
               str_contains($path, '/logout') ||
               str_contains($path, '/authenticate') ||
               str_contains($path, '/auth') ||
               str_contains($path, '/password') ||
               str_contains($path, '/register');
    }

    /**
     * Verifica si es gestión de usuarios
     */
    private function esGestionUsuarios(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_contains($path, '/users') || 
               str_contains($path, '/user') ||
               $request->is('admin/users*') ||
               $request->is('admin/user*');
    }

    /**
     * Verifica si es gestión de apertura/cierre - PERMITIR SIEMPRE
     */
    private function esGestionAperturaCierre(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_contains($path, 'apertura-cierre-dia') ||
               str_contains($path, 'apertura_cierre_dia') ||
               str_contains($path, 'AperturaCierreDia');
    }
}
