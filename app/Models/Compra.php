<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compra extends Model
{
    protected $table = 'Compra';
    protected $primaryKey = 'CompraID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'TipoComprobanteID',
        'Serie',
        'Numero',
        'FechaEmision',
        'NombreProveedor',
        'ProductoServicio',
        'Cantidad',
        'PrecioUnitario',
        'Total',
        'Observaciones',
        'Activo',
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Cantidad' => 'float',
        'PrecioUnitario' => 'decimal:2',
        'Total' => 'decimal:2',
        'Activo' => 'boolean',
    ];

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'TipoComprobanteID', 'TipoComprobanteID');
    }

    public function scopeActivos($query)
    {
        return $query->where('Activo', true);
    }
}
