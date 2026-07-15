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
use App\Models\ProposicionCredito;
use App\Models\AperturaCierreDia;
use App\Models\SolicitudExoneracion;
use App\Models\AprobacionExoneracion;
use App\Models\Gasto;
use App\Models\Compra;
use App\Models\Cuota;
use App\Models\Negocio;
use App\Models\DocumentoCliente;
use App\Models\Mora;
use App\Models\AprobacionProposicion;
use App\Models\CuentaTesoreria;
use App\Models\MovimientoTesoreria;
use App\Models\PrestamoBancario;
use App\Models\CuotaPrestamoBancario;
use App\Models\PagoPrestamoBancario;
use App\Models\RosCaso;
use App\Models\RosPersona;
use App\Models\RosOperacion;
use App\Models\RosSenalAlerta;
use App\Models\RosTipologia;
use App\Models\RosAdjunto;
use App\Observers\AuditObserver;
use App\Observers\RosAuditObserver;

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
        ProposicionCredito::observe(AuditObserver::class);
        AperturaCierreDia::observe(AuditObserver::class);
        SolicitudExoneracion::observe(AuditObserver::class);
        AprobacionExoneracion::observe(AuditObserver::class);
        Gasto::observe(AuditObserver::class);
        Compra::observe(AuditObserver::class);
        Cuota::observe(AuditObserver::class);
        Negocio::observe(AuditObserver::class);
        DocumentoCliente::observe(AuditObserver::class);
        Mora::observe(AuditObserver::class);
        AprobacionProposicion::observe(AuditObserver::class);
        CuentaTesoreria::observe(AuditObserver::class);
        MovimientoTesoreria::observe(AuditObserver::class);
        PrestamoBancario::observe(AuditObserver::class);
        CuotaPrestamoBancario::observe(AuditObserver::class);
        PagoPrestamoBancario::observe(AuditObserver::class);
        RosCaso::observe(RosAuditObserver::class);
        RosPersona::observe(RosAuditObserver::class);
        RosOperacion::observe(RosAuditObserver::class);
        RosSenalAlerta::observe(RosAuditObserver::class);
        RosTipologia::observe(RosAuditObserver::class);
        RosAdjunto::observe(RosAuditObserver::class);
    }

    public function register()
    {
        //
    }
}
