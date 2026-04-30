<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Proveedor;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProveedorPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_proveedor');
    }

    public function view(User $user, Proveedor $proveedor): bool
    {
        return $user->can('view_proveedor');
    }

    public function create(User $user): bool
    {
        return $user->can('create_proveedor');
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->can('update_proveedor');
    }

    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->can('delete_proveedor');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('{{ DeleteAny }}');
    }

    public function forceDelete(User $user, Proveedor $proveedor): bool
    {
        return $user->can('{{ ForceDelete }}');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('{{ ForceDeleteAny }}');
    }

    public function restore(User $user, Proveedor $proveedor): bool
    {
        return $user->can('{{ Restore }}');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('{{ RestoreAny }}');
    }

    public function replicate(User $user, Proveedor $proveedor): bool
    {
        return $user->can('{{ Replicate }}');
    }

    public function reorder(User $user): bool
    {
        return $user->can('{{ Reorder }}');
    }
}
