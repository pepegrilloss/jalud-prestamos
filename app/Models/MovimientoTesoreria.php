<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MovimientoTesoreria extends Model
{
    public const TIPO_APERTURA = 'APERTURA';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';
    public const TIPO_EXTORNO = 'EXTORNO';
    public const TIPO_PAGO_PRESTAMO_BANCARIO = 'PAGO_PRESTAMO_BANCARIO';
    public const TIPO_EXTORNO_PAGO_PRESTAMO = 'EXTORNO_PAGO_PRESTAMO';

    public const CUENTA_BANCARIA = 'CUENTA_BANCARIA';
    public const CAJA_GERENCIA = 'CAJA_GERENCIA';
    public const PRESTAMO_BANCARIO = 'PRESTAMO_BANCARIO';
    public const APERTURA = 'APERTURA';

    protected $table = 'tesoreria_movimientos';
    protected $primaryKey = 'MovimientoTesoreriaID';

    protected $fillable = [
        'Tipo', 'OrigenTipo', 'CuentaOrigenID', 'CuentaOrigenNombre', 'DestinoTipo',
        'CuentaDestinoID', 'CuentaDestinoNombre', 'Monto', 'FechaContable',
        'FechaMovimiento', 'Concepto', 'Observaciones', 'UsuarioID',
        'MovimientoOriginalID', 'SaldoAnteriorOrigen', 'SaldoNuevoOrigen',
        'SaldoAnteriorDestino', 'SaldoNuevoDestino', 'PrestamoBancarioID',
        'CuotaPrestamoBancarioID',
    ];

    protected $casts = [
        'Monto' => 'decimal:2',
        'FechaContable' => 'date',
        'FechaMovimiento' => 'datetime',
        'SaldoAnteriorOrigen' => 'decimal:2',
        'SaldoNuevoOrigen' => 'decimal:2',
        'SaldoAnteriorDestino' => 'decimal:2',
        'SaldoNuevoDestino' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Los movimientos de Tesoreria son inalterables.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Los movimientos de Tesoreria no pueden eliminarse.');
        });
    }

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(CuentaTesoreria::class, 'CuentaOrigenID', 'CuentaTesoreriaID');
    }

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(CuentaTesoreria::class, 'CuentaDestinoID', 'CuentaTesoreriaID');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioID');
    }

    public function movimientoOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'MovimientoOriginalID', 'MovimientoTesoreriaID');
    }

    public function extorno(): HasOne
    {
        return $this->hasOne(self::class, 'MovimientoOriginalID', 'MovimientoTesoreriaID');
    }

    public function prestamoBancario(): BelongsTo
    {
        return $this->belongsTo(PrestamoBancario::class, 'PrestamoBancarioID', 'PrestamoBancarioID');
    }

    public function cuotaPrestamoBancario(): BelongsTo
    {
        return $this->belongsTo(CuotaPrestamoBancario::class, 'CuotaPrestamoBancarioID', 'CuotaPrestamoBancarioID');
    }
}
