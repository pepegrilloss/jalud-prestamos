<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credito extends Model
{
    protected $table = 'Credito';
    protected $primaryKey = 'CreditoID';
    public $timestamps = false;
    public $incrementing = true;

    protected $guarded = [
        'CreditoID', // NUNCA permitir actualizar la columna IDENTITY
    ];

    protected $with = ['proposicion', 'tipoPago'];

    protected $casts = [
        'FechaGeneracion' => 'datetime',
        'FechaInicio' => 'date',
        'FechaVencimiento' => 'date',
        'Activo' => 'boolean',
    ];

    // --- Relaciones ---

    public function proposicion()
    {
        return $this->belongsTo(ProposicionCredito::class, 'ProposicionCreditoID');
    }

    public function tipoPago()
    {
        return $this->belongsTo(TipoPago::class, 'TipoPagoID');
    }

    public function userGeneracion()
    {
        return $this->belongsTo(User::class, 'UserGeneracionID');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'CreditoID');
    }

    public function cuotas()
    {
        return $this->hasMany(Cuota::class, 'CreditoID', 'CreditoID');
    }
}
