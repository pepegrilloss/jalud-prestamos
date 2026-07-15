<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaTesoreria extends Model
{
    public const ESTADO_ACTIVA = 'ACTIVA';
    public const ESTADO_INACTIVA = 'INACTIVA';

    protected $table = 'tesoreria_cuentas';
    protected $primaryKey = 'CuentaTesoreriaID';

    protected $fillable = [
        'Banco',
        'NumeroCuenta',
        'TipoCuenta',
        'SaldoActual',
        'FechaUltimoMovimiento',
        'Estado',
    ];

    protected $casts = [
        'SaldoActual' => 'decimal:2',
        'FechaUltimoMovimiento' => 'datetime',
    ];

    public function movimientosOrigen(): HasMany
    {
        return $this->hasMany(MovimientoTesoreria::class, 'CuentaOrigenID', 'CuentaTesoreriaID');
    }

    public function movimientosDestino(): HasMany
    {
        return $this->hasMany(MovimientoTesoreria::class, 'CuentaDestinoID', 'CuentaTesoreriaID');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->Banco} - {$this->NumeroCuenta}");
    }
}
