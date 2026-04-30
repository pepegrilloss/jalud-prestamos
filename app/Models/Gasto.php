<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

class Gasto extends Model
{
    use BelongsToSede;
    protected $table = 'Gasto';
    protected $primaryKey = 'GastoID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'TipoComprobanteGastoID',
        'Numero',
        'FechaEmision',
        'ProveedorID',
        'MotivoID',
        'MetodoGasto',
        'Total',
        'Observaciones',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Total' => 'decimal:2',
        'Activo' => 'boolean',
    ];

    public function tipoComprobanteGasto(): BelongsTo
    {
        return $this->belongsTo(TipoComprobanteGasto::class, 'TipoComprobanteGastoID', 'TipoComprobanteGastoID');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'MotivoID', 'MotivoID');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'ProveedorID', 'ProveedorID');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(GastoDetalle::class, 'GastoID', 'GastoID');
    }

    public function scopeActivos($query)
    {
        return $query->where('Activo', true);
    }
}
