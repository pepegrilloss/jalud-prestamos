<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProposicionCredito;
use App\Observers\ProposicionCreditoObserver;

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
        ProposicionCredito::observe(ProposicionCreditoObserver::class);
    }
}

