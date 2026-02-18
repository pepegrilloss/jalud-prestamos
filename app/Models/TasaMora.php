<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TasaMora extends Model
{
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
