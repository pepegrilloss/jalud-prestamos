<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Log;
use App\Policies\LogPolicy;
use App\Models\TraspasoZonaCliente;
use App\Policies\TraspasoZonaClientePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Log::class => LogPolicy::class,
        TraspasoZonaCliente::class => TraspasoZonaClientePolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
