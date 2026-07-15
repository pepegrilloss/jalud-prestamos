<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PagoPrestamoBancario extends Model
{
    protected $table = 'tesoreria_prestamo_pagos';
    protected $primaryKey = 'PagoPrestamoBancarioID';

    protected $fillable = [
        'PrestamoBancarioID', 'CuotaPrestamoBancarioID', 'MovimientoTesoreriaID', 'Monto',
        'FechaContable', 'FechaRegistro', 'Concepto', 'Observaciones', 'UsuarioID', 'PagoOriginalID',
    ];

    protected $casts = [
        'Monto' => 'decimal:2', 'FechaContable' => 'date', 'FechaRegistro' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los pagos de prestamos bancarios son inalterables.'));
        static::deleting(fn () => throw new \LogicException('Los pagos de prestamos bancarios no pueden eliminarse.'));
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoBancario::class, 'PrestamoBancarioID', 'PrestamoBancarioID');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaPrestamoBancario::class, 'CuotaPrestamoBancarioID', 'CuotaPrestamoBancarioID');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoTesoreria::class, 'MovimientoTesoreriaID', 'MovimientoTesoreriaID');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioID');
    }

    public function pagoOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'PagoOriginalID', 'PagoPrestamoBancarioID');
    }

    public function extorno(): HasOne
    {
        return $this->hasOne(self::class, 'PagoOriginalID', 'PagoPrestamoBancarioID');
    }
}
