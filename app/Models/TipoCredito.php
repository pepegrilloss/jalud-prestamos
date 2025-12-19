<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCredito extends Model
{
    protected $table = 'TipoCredito';
    protected $primaryKey = 'TipoCreditoID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Descripcion',
        'Activo',
        'FechaCreacion',
        'FechaModificacion',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
