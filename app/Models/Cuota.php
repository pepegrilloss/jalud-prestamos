<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $table = 'cuota';
    protected $primaryKey = 'CuotaID';
    public $timestamps = false;

    protected $fillable = [
        'CreditoID',
        'NumeroCuota',
        'FechaVencimiento',
        'MontoCuota',
        'Estado',
        'DiasAtraso',
        'MontoMora',
        'FechaPago',
        'FechaCreacion',
        'FechaModificacion',
        'Activo'
    ];

    protected $dates = [
        'FechaVencimiento',
        'FechaPago',
        'FechaCreacion',
        'FechaModificacion'
    ];

    protected $casts = [
        'FechaVencimiento' => 'datetime:Y-m-d',
        'FechaPago' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean'
    ];

    // Relaciones
    public function credito()
    {
        return $this->belongsTo(Credito::class, 'CreditoID', 'CreditoID');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'CuotaID', 'CuotaID');
    }

    // Estados
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_PAGADA = 'PAGADA';
    const ESTADO_MORA = 'MORA';
    const ESTADO_NORMAL = 'NORMAL';
    const ESTADO_DOMINGO = 'DOMINGO';
    const ESTADO_FERIADO = 'FERIADO';

    public function estaVencida()
    {
        return now()->isAfter($this->FechaVencimiento) && $this->Estado === self::ESTADO_PENDIENTE;
    }

    public function diasAtraso()
    {
        if ($this->estaVencida()) {
            return now()->diffInDays($this->FechaVencimiento);
        }
        return 0;
    }

    // Propiedades calculadas dinámicamente desde la tabla pago
    public function getMontoPagadoAttribute()
    {
        return $this->pagos()
            ->where('Activo', 1)
            ->sum('MontoPagado') ?? 0;
    }

    public function getSaldoPendienteAttribute()
    {
        return max(0, $this->MontoCuota - $this->MontoPagado);
    }
}
