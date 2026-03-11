<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class TipoComprobante extends Model
{
    use BelongsToSede;
    protected $table = 'TipoComprobante';
    protected $primaryKey = 'TipoComprobanteID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'Nombre',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
