<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Observers\AuditObserver;

class AuditServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Registrar observers en todos los modelos que quieras auditar
        User::observe(AuditObserver::class);
        Cliente::observe(AuditObserver::class);
        Credito::observe(AuditObserver::class);
        Cuota::observe(AuditObserver::class);
        Pago::observe(AuditObserver::class);
    }

    public function register()
    {
        //
    }
}
