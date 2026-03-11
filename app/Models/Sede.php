<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $table = 'Sede';
    protected $primaryKey = 'SedeID';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Codigo',
        'Activo',
        'FechaCreacion',
        'FechaModificacion',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'SedeID', 'SedeID');
    }
}
