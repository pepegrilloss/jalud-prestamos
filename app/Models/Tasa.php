<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasa extends Model
{
    protected $table = 'Tasa';
    protected $primaryKey = 'TasaID';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Valor',
        'Activo',
        'FechaCreacion',
        'FechaModificacion'
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'Valor' => 'decimal:2',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}