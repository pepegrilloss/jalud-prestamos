<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Credito;
use Illuminate\Auth\Access\HandlesAuthorization;

class CreditoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_credito');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Credito $credito): bool
    {
        return $user->can('view_credito');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_credito');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Credito $credito): bool
    {
        return $user->can('update_credito');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Credito $credito): bool
    {
        return $user->can('delete_credito');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_credito');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Credito $credito): bool
    {
        return $user->can('force_delete_credito');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_credito');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Credito $credito): bool
    {
        return $user->can('restore_credito');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_credito');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Credito $credito): bool
    {
        return $user->can('replicate_credito');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_credito');
    }
}
