<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoDetalle extends Model
{
    protected $table = 'GastoDetalle';
    protected $primaryKey = 'GastoDetalleID';
    public $timestamps = false;

    protected $fillable = [
        'GastoID',
        'Descripcion',
        'Monto',
    ];

    protected $casts = [
        'Monto' => 'decimal:2',
    ];

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class, 'GastoID', 'GastoID');
    }
}
