<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Cliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Promotor Cobrador')) {
            return true;
        }

        return $user->can('view_any_cliente::proposicion');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cliente $cliente): bool
    {
        if ($user->hasRole('Promotor Cobrador')) {
            $promotorCobrador = $user->promotorCobrador;
            
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                // Verificar si el cliente tiene al menos una proposición en la zona del promotor
                return $cliente->proposiciones()
                    ->where('ZonaID', $promotorCobrador->ZonaID)
                    ->exists();
            }

            return false;
        }

        return $user->can('view_cliente::proposicion');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Los Promotores Cobradores NO pueden crear clientes
        if ($user->hasRole('Promotor Cobrador')) {
            return false;
        }

        return $user->can('create_cliente::proposicion');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        // Los Promotores Cobradores NO pueden editar clientes
        if ($user->hasRole('Promotor Cobrador')) {
            return false;
        }

        return $user->can('update_cliente::proposicion');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        // Los Promotores Cobradores NO pueden eliminar clientes
        if ($user->hasRole('Promotor Cobrador')) {
            return false;
        }

        return $user->can('delete_cliente::proposicion');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cliente::proposicion');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Cliente $cliente): bool
    {
        return $user->can('force_delete_cliente::proposicion');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_cliente::proposicion');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Cliente $cliente): bool
    {
        return $user->can('restore_cliente::proposicion');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_cliente::proposicion');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Cliente $cliente): bool
    {
        return $user->can('replicate_cliente::proposicion');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_cliente::proposicion');
    }
}
