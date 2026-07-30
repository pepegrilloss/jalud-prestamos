<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoBancario extends Model
{
    public const ESTADO_VIGENTE = 'VIGENTE';

    public const ESTADO_CANCELADO = 'CANCELADO';

    public const ESTADO_CANCELADO_ANTICIPADO = 'CANCELADO_ANTICIPADO';

    protected $table = 'tesoreria_prestamos_bancarios';

    protected $primaryKey = 'PrestamoBancarioID';

    protected $fillable = [
        'CuentaTesoreriaID', 'Banco', 'Cliente', 'CuentaPrestamo', 'Operacion', 'MontoPrestamo',
        'FechaDesembolso', 'FechaVencimiento', 'NumeroCuotas', 'DiaPago', 'PagoMensual',
        'TEA', 'TED', 'Estado', 'Observaciones',
    ];

    protected $casts = [
        'MontoPrestamo' => 'decimal:2', 'FechaDesembolso' => 'date', 'FechaVencimiento' => 'date',
        'PagoMensual' => 'decimal:2', 'TEA' => 'decimal:6', 'TED' => 'decimal:6',
    ];

    public function cuentaTesoreria(): BelongsTo
    {
        return $this->belongsTo(CuentaTesoreria::class, 'CuentaTesoreriaID', 'CuentaTesoreriaID');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaPrestamoBancario::class, 'PrestamoBancarioID', 'PrestamoBancarioID')->orderBy('Numero');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoPrestamoBancario::class, 'PrestamoBancarioID', 'PrestamoBancarioID')->latest('FechaRegistro');
    }

    public function getNombreBancoAttribute(): string
    {
        return $this->Banco ?? $this->cuentaTesoreria?->Banco ?? 'Banco no disponible';
    }

    public function getFuentePagoAttribute(): string
    {
        return $this->cuentaTesoreria?->NombreCompleto ?? 'Caja Abierta - Gerencia';
    }

    public function getCapitalPendienteAttribute(): float
    {
        return round((float) $this->cuotas()
            ->where('Estado', CuotaPrestamoBancario::ESTADO_PENDIENTE)
            ->sum('Capital'), 2);
    }
}
