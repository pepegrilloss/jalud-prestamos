<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class TipoExoneracion extends Model
{
    use BelongsToSede;
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
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
