<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'Compra';
    protected $primaryKey = 'CompraID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'TipoComprobanteID',
        'Numero',
        'FechaEmision',
        'NombreProveedor',
        'SubtotalBase',
        'MontoIGV',
        'Total',
        'Observaciones',
        'Activo',
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

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'CompraID', 'CompraID');
    }

    public function scopeActivos($query)
    {
        return $query->where('Activo', true);
    }
}
