<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Excedente;
use App\Models\TransferenciaSede;
use App\Models\FondoSede;
use App\Models\MovimientoFondo;
use App\Models\SolicitudResolucionExcedente;
use App\Observers\AuditObserver;

class AuditServiceProvider extends ServiceProvider
{
    public function boot()
    {
        User::observe(AuditObserver::class);
        Cliente::observe(AuditObserver::class);
        Credito::observe(AuditObserver::class);
        Pago::observe(AuditObserver::class);
        Excedente::observe(AuditObserver::class);
        TransferenciaSede::observe(AuditObserver::class);
        FondoSede::observe(AuditObserver::class);
        MovimientoFondo::observe(AuditObserver::class);
        SolicitudResolucionExcedente::observe(AuditObserver::class);
    }

    public function register()
    {
        //
    }
}
