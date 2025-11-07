<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotorCobrador extends Model
{
    protected $table = 'PromotorCobrador';
    protected $primaryKey = 'PromotorCobradorID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Descripcion',
        'Ciudad',
        'Activo',
        'FechaCreacion',
        'FechaModificacion'
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}