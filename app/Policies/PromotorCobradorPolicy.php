<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PromotorCobrador;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromotorCobradorPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_promotor::cobrador');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('view_promotor::cobrador');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_promotor::cobrador');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('update_promotor::cobrador');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('delete_promotor::cobrador');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_promotor::cobrador');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('force_delete_promotor::cobrador');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_promotor::cobrador');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('restore_promotor::cobrador');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_promotor::cobrador');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, PromotorCobrador $promotorCobrador): bool
    {
        return $user->can('replicate_promotor::cobrador');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_promotor::cobrador');
    }
}
