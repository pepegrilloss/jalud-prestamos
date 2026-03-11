<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

class Giro extends Model
{
    use BelongsToSede;
    protected $table = 'Giro';
    protected $primaryKey = 'GiroID';
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

    public function subGiros(): HasMany
    {
        return $this->hasMany(SubGiro::class, 'GiroID', 'GiroID');
    }
}