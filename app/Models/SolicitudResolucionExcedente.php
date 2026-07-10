<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class SolicitudResolucionExcedente extends Model
{
    use HasFactory, BelongsToSede;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            app(\App\Services\SedeIntegrityService::class)->assertSolicitudResolucionConsistente($model);
        });

        static::updating(function ($model) {
            app(\App\Services\SedeIntegrityService::class)->assertSolicitudResolucionConsistente($model);
        });
    }

    protected $table = 'solicitudes_resolucion_excedente';
    protected $primaryKey = 'SolicitudID';

    protected $fillable = [
        'ExcedenteID',
        'MontoAplicar',
        'ClienteOrigenID',
        'TipoResolucion',
        'ClienteDestinoID',
        'CreditoDestinoID',
        'DatosValeCaja',
        'Observaciones',
        'Estado',
        'UserSolicitanteID',
        'UserAprobadorID',
        'SedeID',
        'PagoOrigenID',
        'CreditoOrigenID',
        'FechaCierre',
    ];

    public function excedente()
    {
        return $this->belongsTo(Excedente::class, 'ExcedenteID', 'ExcedenteID');
    }

    public function clienteOrigen()
    {
        return $this->belongsTo(Cliente::class, 'ClienteOrigenID', 'ClienteID');
    }

    public function clienteDestino()
    {
        return $this->belongsTo(Cliente::class, 'ClienteDestinoID', 'ClienteID');
    }

    public function creditoDestino()
    {
        return $this->belongsTo(Credito::class, 'CreditoDestinoID', 'CreditoID');
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'UserSolicitanteID');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'UserAprobadorID');
    }

    public function pagoOrigen()
    {
        return $this->belongsTo(Pago::class, 'PagoOrigenID', 'PagoID');
    }

    public function creditoOrigen()
    {
        return $this->belongsTo(Credito::class, 'CreditoOrigenID', 'CreditoID');
    }
}
