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
    public static function bootBelongsToSede(): void
    {
        // Auto-asignar SedeID al crear un registro
        static::creating(function ($model) {
            if (empty($model->SedeID) && auth()->check()) {
                $user = auth()->user();
                if (($user->esAdmin() || $user->can('ver_todas_las_sedes')) && session('sede_activa')) {
                    // Admin con sede seleccionada: usar la sede de la sesión
                    $model->SedeID = session('sede_activa');
                } else {
                    // Usuario normal o admin sin sede seleccionada: usar la sede del usuario
                    $model->SedeID = $user->SedeID;
                }
            }
        });

        // Global Scope: filtrar automáticamente por sede
        static::addGlobalScope('sede', function (Builder $query) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->esAdmin() || $user->can('ver_todas_las_sedes')) {
                // Admin: filtrar solo si tiene una sede seleccionada en sesión
                $sedeActiva = session('sede_activa');
                if ($sedeActiva) {
                    $query->where($query->getModel()->getTable() . '.SedeID', $sedeActiva);
                }
                // Si no tiene sede en sesión → ve todo (no filtra)
            } else {
                // Usuario normal: filtrar siempre por su sede asignada
                $sedeID = $user->SedeID;
                if ($sedeID) {
                    $query->where($query->getModel()->getTable() . '.SedeID', $sedeID);
                }
            }
        });
    }

    /**
     * Relación con la sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }
}
