<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class Zona extends Model
{
    use BelongsToSede;
    protected $table = 'Zona';
    protected $primaryKey = 'ZonaID';
    public $timestamps = false;

    protected $fillable = [
        'CiudadID',
        'Nombre',
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

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'CiudadID', 'CiudadID');
    }
}