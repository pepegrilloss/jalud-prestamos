<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class NivelAprobacion extends Model
{
    use BelongsToSede;
    protected $table = 'NivelAprobacion';
    protected $primaryKey = 'NivelAprobacionID';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'MontoMinimo',
        'MontoMaximo',
        'Orden',
        'Activo',
        'FechaCreacion',
        'FechaModificacion',
        'SedeID',
    ];

    protected $casts = [
        'MontoMinimo' => 'decimal:2',
        'MontoMaximo' => 'decimal:2',
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
