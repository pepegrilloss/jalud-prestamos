<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciudad extends Model
{
    protected $table = 'Ciudad';
    protected $primaryKey = 'CiudadID';
    public $timestamps = false;

    protected $fillable = [
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

    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class, 'CiudadID', 'CiudadID');
    }
}