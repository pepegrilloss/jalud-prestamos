<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TraspasoZonaCliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class TraspasoZonaClientePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return $user->can('view_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return $user->can('update_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return $user->can('delete_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_traspaso::zona::cliente');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, TraspasoZonaCliente $traspasoZonaCliente): bool
    {
        return false;
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return false;
    }
}
