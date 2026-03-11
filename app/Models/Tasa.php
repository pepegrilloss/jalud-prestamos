<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Tasa extends Model
{
    use BelongsToSede;
    protected $table = 'Tasa';
    protected $primaryKey = 'TasaID';
    public $timestamps = true;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'Nombre',
        'Valor',
        'Activo',
        'Dias',
        'Cuotas',
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'Valor' => 'decimal:2',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Dias' => 'integer',
        'Cuotas' => 'integer',
    ];
}