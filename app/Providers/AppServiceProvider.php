<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use App\Models\ProposicionCredito;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\AperturaCierreDia;
use App\Observers\ProposicionCreditoObserver;
use App\Observers\CreditoObserver;
use App\Observers\PagoObserver;
use App\Observers\AperturaCierreDiaObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gerencia may read its maintenance catalogs without granting write permissions.
        Gate::before(function ($user, string $ability, array $arguments) {
            if (! $user
                || Filament::getCurrentPanel()?->getId() !== 'gerencia'
                || ! $user->puedeAccederAGerencia()
                || ! in_array($ability, ['viewAny', 'view'], true)) {
                return null;
            }

            $maintenanceModels = [
                \App\Models\Ciudad::class,
                \App\Models\Giro::class,
                \App\Models\NivelAprobacion::class,
                \App\Models\Motivo::class,
                \App\Models\PromotorCobrador::class,
                \App\Models\Proveedor::class,
                \App\Models\Sede::class,
                \App\Models\SubGiro::class,
                \App\Models\Tasa::class,
                \App\Models\TasaMora::class,
                \App\Models\TipoCredito::class,
                \App\Models\TipoComprobante::class,
                \App\Models\TipoComprobanteGasto::class,
                \App\Models\TipoExoneracion::class,
                \App\Models\TipoIgv::class,
                \App\Models\TipoPago::class,
                \App\Models\Zona::class,
            ];

            $subject = $arguments[0] ?? null;
            $model = is_string($subject) ? $subject : ($subject instanceof \Illuminate\Database\Eloquent\Model ? $subject::class : null);

            return in_array($model, $maintenanceModels, true) ? true : null;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        ProposicionCredito::observe(ProposicionCreditoObserver::class);
        Credito::observe(CreditoObserver::class);
        Pago::observe(PagoObserver::class);
        AperturaCierreDia::observe(AperturaCierreDiaObserver::class);
    }
}

