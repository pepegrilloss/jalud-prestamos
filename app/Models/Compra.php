<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

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
        'Observaciones',
        'Activo',
        'SedeID',
        'UsuarioRegistro',
        'UsuarioModificacion',
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'SubtotalBase' => 'decimal:2',
        'MontoIGV' => 'decimal:2',
        'Total' => 'decimal:2',
        'Activo' => 'boolean',
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
}
