<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zona extends Model
{
    protected $table = 'Zona';
    protected $primaryKey = 'ZonaID';
    public $timestamps = false;

    protected $fillable = [
        'CiudadID',
        'Nombre',
        'Activo',
        'FechaCreacion',
        'FechaModificacion'
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