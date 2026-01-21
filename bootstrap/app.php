<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Rate Limiting en login
        $middleware->append(\App\Http\Middleware\RateLimitLogin::class);
        
        // Validar estado del día para operaciones - DESHABILITADO TEMPORALMENTE
        // $middleware->append(\App\Http\Middleware\ValidarDiaAperturado::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
