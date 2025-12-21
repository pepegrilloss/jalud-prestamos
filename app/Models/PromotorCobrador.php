<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotorCobrador extends Model
{
    protected $table = 'PromotorCobrador';
    protected $primaryKey = 'PromotorCobradorID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Descripcion',
        'CiudadID',
        'Activo',
        'FechaCreacion',
        'FechaModificacion'
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'CiudadID', 'CiudadID');
    }
}