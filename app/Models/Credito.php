<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Credito extends Model
{
    use BelongsToSede;
    protected $table = 'Credito';
    protected $primaryKey = 'CreditoID';
    public $timestamps = false;
    public $incrementing = true;

    protected $guarded = [
        'CreditoID', // NUNCA permitir actualizar la columna IDENTITY
    ];

    // NOTA: No usar protected $with aquí. Usar ->with() explícito en cada Resource/query donde se necesite.
    // Esto evita cargar relaciones innecesarias en counts, updates masivos y observers.

    protected $casts = [
        'FechaGeneracion' => 'datetime',
        'FechaInicio' => 'date',
        'FechaVencimiento' => 'date',
        'FechaCierre' => 'datetime',
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

    public function moras()
    {
        return $this->hasMany(Mora::class, 'CreditoID');
    }
}
