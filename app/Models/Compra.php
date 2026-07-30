<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use BelongsToSede;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                $model->FechaCreacion = $fecha;
                $model->FechaModificacion = $fecha;
            }
        });

        static::updating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                $model->FechaModificacion = $fecha;
            }
        });
    }

    protected $table = 'Compra';

    protected $primaryKey = 'CompraID';

    public $timestamps = true;

    const CREATED_AT = 'FechaCreacion';

    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'TipoComprobanteID',
        'Numero',
        'FechaEmision',
        'ProveedorID',
        'SubtotalBase',
        'MontoIGV',
        'Total',
        'TipoIGV',
        'TipoCompra',
        'EstadoPago',
        'FechaPago',
        'UsuarioPagoID',
        'OrigenTesoreriaTipo',
        'CuentaTesoreriaID',
        'Observaciones',
        'Activo',
        'SedeID',
        'UsuarioRegistro',
        'UsuarioModificacion',
        'FechaCierre',
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'FechaCierre' => 'datetime',
        'SubtotalBase' => 'decimal:2',
        'MontoIGV' => 'decimal:2',
        'Total' => 'decimal:2',
        'Activo' => 'boolean',
        'FechaPago' => 'datetime',
    ];

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'TipoComprobanteID', 'TipoComprobanteID');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'ProveedorID', 'ProveedorID');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'CompraID', 'CompraID');
    }

    public function scopeActivos($query)
    {
        return $query->where('Activo', true);
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioRegistro', 'id');
    }

    public function usuarioModificacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioModificacion', 'id');
    }

    public function usuarioPago(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioPagoID', 'id');
    }

    public function cuentaTesoreria(): BelongsTo
    {
        return $this->belongsTo(CuentaTesoreria::class, 'CuentaTesoreriaID', 'CuentaTesoreriaID');
    }

    public function getFuenteTesoreriaAttribute(): string
    {
        return match ($this->OrigenTesoreriaTipo) {
            MovimientoTesoreria::CAJA_GERENCIA => 'Caja Abierta - Gerencia',
            MovimientoTesoreria::CUENTA_BANCARIA => $this->cuentaTesoreria?->NombreCompleto ?? 'Cuenta bancaria',
            default => 'Caja Chica / registro anterior',
        };
    }
}
