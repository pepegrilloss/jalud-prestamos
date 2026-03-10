<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoComprobanteGasto extends Model
{
    protected $table = 'TipoComprobanteGasto';
    protected $primaryKey = 'TipoComprobanteGastoID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'Nombre',
        'Activo',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
