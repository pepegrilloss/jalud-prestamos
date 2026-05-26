<?php

namespace App\Traits;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToSede
 * 
 * Proporciona funcionalidad multi-sede a los modelos:
 * 1. Auto-asigna SedeID al crear un registro (toma la sede del usuario autenticado)
 * 2. Aplica un Global Scope que filtra automáticamente por sede (excepto para super_admin)
 * 
 * Uso: Agregar `use BelongsToSede;` en el modelo y `'SedeID'` en $fillable
 */
trait BelongsToSede
{
    /**
     * Caché estático por request para evitar resolver auth/permisos en cada query.
     * Se resetea automáticamente al inicio de cada request HTTP.
     */
    private static ?array $sedeFilterCache = null;

    public static function bootBelongsToSede(): void
    {
        // Auto-asignar SedeID al crear un registro
        static::creating(function ($model) {
            if (empty($model->SedeID) && auth()->check()) {
                $filter = self::resolveSedeFilter();
                if ($filter['sedeActiva']) {
                    $model->SedeID = $filter['sedeActiva'];
                } else {
                    $model->SedeID = $filter['sedeUsuario'];
                }
            }
        });

        // Global Scope: filtrar automáticamente por sede (con caché)
        static::addGlobalScope('sede', function (Builder $query) {
            if (!auth()->check()) {
                return;
            }

            $filter = self::resolveSedeFilter();

            if ($filter['esPrivilegiado']) {
                if ($filter['sedeActiva']) {
                    $query->where($query->getModel()->getTable() . '.SedeID', $filter['sedeActiva']);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Usuario normal: filtrar siempre por su sede asignada
                if ($filter['sedeUsuario']) {
                    $query->where($query->getModel()->getTable() . '.SedeID', $filter['sedeUsuario']);
                }
            }
        });
    }

    /**
     * Resolver y cachear la información de filtrado de sede.
     * Solo se ejecuta UNA VEZ por request HTTP.
     */
    private static function resolveSedeFilter(): array
    {
        if (self::$sedeFilterCache !== null) {
            return self::$sedeFilterCache;
        }

        $user = auth()->user();

        self::$sedeFilterCache = [
            'esPrivilegiado' => $user->isPrivileged(),
            'sedeActiva' => session('sede_activa'),
            'sedeUsuario' => $user->SedeID,
        ];

        return self::$sedeFilterCache;
    }

    /**
     * Resetear caché de sede (útil para testing o cambio de sede en sesión).
     */
    public static function resetSedeFilterCache(): void
    {
        self::$sedeFilterCache = null;
    }

    /**
     * Relación con la sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }
}
