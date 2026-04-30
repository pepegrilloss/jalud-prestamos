<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Proveedor extends Model
{
    use BelongsToSede;
    protected $table = 'Proveedor';
    protected $primaryKey = 'ProveedorID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'Codigo',
        'Nombre',
        'RUC',
        'Direccion',
        'Telefono',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];
}
