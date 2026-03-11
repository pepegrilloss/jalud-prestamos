<?php

namespace App\Services;

use App\Models\AperturaCierreDia;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;

class ValidacionDiaService
{
    /**
     * Valida que el día esté abierto para operaciones
     * Excepciones: Gestión de usuarios (admin) y apertura/cierre de día
     */
    public static function validarParaOperacion(string $modelClass = '', bool $lanzarExcepcion = false): bool
    {
        // Excepciones permitidas
        if (self::esExceptionPermitida($modelClass)) {
            return true;
        }

        $abierto = AperturaCierreDia::estaAbierto();

        if (!$abierto) {
            Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
        }

        return $abierto;
    }

    /**
     * Verifica si el modelo está exento de validación
     */
    private static function esExceptionPermitida(string $modelClass): bool
    {
        // Lista de modelos permitidos incluso con día cerrado
        $excepciones = [
            'App\Models\User',
            'App\Models\AperturaCierreDia',
            'User',
            'AperturaCierreDia',
        ];

        return in_array($modelClass, $excepciones) || 
               in_array(class_basename($modelClass), $excepciones);
    }

    /**
     * Obtiene información del estado actual
     */
    public static function obtenerEstado(): array
    {
        $registro = AperturaCierreDia::hoyOHoy();

        return [
            'abierto' => AperturaCierreDia::estaAbierto(),
            'estado' => AperturaCierreDia::estadoDiaActual(),
            'registro' => $registro,
            'mensaje' => AperturaCierreDia::estaAbierto() ? 
                '✅ Día abierto - Operaciones permitidas' : 
                '❌ Día cerrado - Contacte administración',
        ];
    }

    /**
     * Valida el acceso a un recurso específico
     */
    public static function validarAccesoRecurso(string $recurso, string $accion = 'crear'): bool
    {
        // Solo admin puede abrir/cerrar día
        if ($recurso === 'AperturaCierreDia') {
            if (!auth()->user()?->esAdmin()) {
                throw new AuthorizationException('Solo administradores pueden gestionar la apertura/cierre de día.');
            }
            return true;
        }

        // Usuarios solo pueden ser creados por admin
        if ($recurso === 'User') {
            return true; // Sin restricción de día para usuarios
        }

        // Para todos los demás recursos, validar día abierto
        return self::validarParaOperacion($recurso);
    }
}
