<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class TipoCredito extends Model
{
    use BelongsToSede;
    protected $table = 'TipoCredito';
    protected $primaryKey = 'TipoCreditoID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Descripcion',
        'Activo',
        'FechaCreacion',
        'FechaModificacion',
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
