<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class TasaMora extends Model
{
    use BelongsToSede;
    protected $table = 'TasaMora';
    protected $primaryKey = 'TasaMoraID';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Porcentaje',
        'Descripcion',
        'Activo',
        'FechaCreacion',
        'FechaModificacion',
        'SedeID',
    ];

    protected $casts = [
        'Porcentaje' => 'decimal:2',
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->Activo = 1;
        });
    }
}
