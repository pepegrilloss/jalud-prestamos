<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $table = 'Gasto';
    protected $primaryKey = 'GastoID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'TipoComprobanteID',
        'Numero',
        'FechaEmision',
        'NombreProveedor',
        'MotivoID',
        'MetodoGasto',
        'Descripcion',
        'Total',
        'Observaciones',
        'Activo',
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Total' => 'decimal:2',
        'Activo' => 'boolean',
    ];

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'TipoComprobanteID', 'TipoComprobanteID');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'MotivoID', 'MotivoID');
    }

    public function scopeActivos($query)
    {
        return $query->where('Activo', true);
    }
}
