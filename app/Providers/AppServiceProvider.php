<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        ProposicionCredito::observe(ProposicionCreditoObserver::class);
        Credito::observe(CreditoObserver::class);
        Pago::observe(PagoObserver::class);
        AperturaCierreDia::observe(AperturaCierreDiaObserver::class);
    }
}

