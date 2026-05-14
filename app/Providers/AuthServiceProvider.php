<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Log;
use App\Policies\LogPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Log::class => LogPolicy::class,
    ];

    public function boot(): void
    {
        // Regla Global: Modo Solo Lectura para "Todas las Sedes"
        Gate::before(function ($user, $ability) {
            // Si es SuperAdmin o tiene permiso para ver todas las sedes, pero está en "Todas las Sedes" (session sede_activa es null)
            if (($user->esAdmin() || $user->can('ver_todas_las_sedes')) && !session('sede_activa')) {
                
                // Prefijos de habilidades que modifican el sistema
                $blockedPrefixes = ['create_', 'update_', 'delete_', 'restore_', 'forceDelete_'];
                
                // Si la habilidad empieza con alguno de los prefijos de modificación, bloqueamos.
                foreach ($blockedPrefixes as $prefix) {
                    if (str_starts_with($ability, $prefix)) {
                        return false; 
                    }
                }
            }
        });

        Gate::authorize('authorizeResourcesByPolicy', static fn() => true);
    }
}
