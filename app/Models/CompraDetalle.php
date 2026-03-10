<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    protected $table = 'CompraDetalle';
    protected $primaryKey = 'CompraDetalleID';
    public $timestamps = false;

    protected $fillable = [
        'CompraID',
        'ProductoServicio',
        'Cantidad',
        'PrecioUnitario',
        'Subtotal',
    ];

    protected $casts = [
        'Cantidad' => 'float',
        'PrecioUnitario' => 'decimal:2',
        'Subtotal' => 'decimal:2',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'CompraID', 'CompraID');
    }
}
