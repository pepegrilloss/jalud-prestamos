<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoExoneracion extends Model
{
    protected $table = 'TipoExoneracion';
    protected $primaryKey = 'TipoExoneracionID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'Codigo',
        'Nombre',
        'Descripcion',
        'Activo',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
