<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoIgv extends Model
{
    protected $table = 'tipo_igv';
    protected $primaryKey = 'TipoIgvID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Nombre',
        'Porcentaje',
        'Activo',
    ];

    protected $casts = [
        'Porcentaje' => 'decimal:2',
        'Activo' => 'boolean',
    ];
}
