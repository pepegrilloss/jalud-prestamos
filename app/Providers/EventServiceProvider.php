<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Listeners\LogAuthenticationListener;
use App\Events\DiaAbierto;
use App\Listeners\CalcularMoraAlAbrirDia;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \Illuminate\Auth\Events\Failed::class => [
            \App\Listeners\LogFailedLoginAttempt::class,
        ],
        DiaAbierto::class => [
            CalcularMoraAlAbrirDia::class,
        ],
    ];

    public function boot()
    {
        Event::subscribe(LogAuthenticationListener::class);
    }

    public function shouldDiscoverEvents()
    {
        return false;
    }
}
