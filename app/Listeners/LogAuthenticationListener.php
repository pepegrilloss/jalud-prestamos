<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Models\Log;

class LogAuthenticationListener
{
    public function handleLogin(Login $event)
    {
        Log::registrar(
            'LOGIN',
            'User',
            $event->user->id,
            null,
            ['user_id' => $event->user->id, 'name' => $event->user->name]
        );
    }

    public function handleLogout(Logout $event)
    {
        Log::registrar(
            'LOGOUT',
            'User',
            $event->user->id,
            ['user_id' => $event->user->id, 'name' => $event->user->name],
            null
        );
    }

    public function subscribe($events)
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
        ];
    }
}
