<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubGiro extends Model
{
    protected $table = 'SubGiro';
    protected $primaryKey = 'SubGiroID';
    public $timestamps = false;

    protected $fillable = [
        'GiroID',
        'Descripcion',
        'Activo',
        'FechaCreacion',
        'FechaModificacion'
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    public function giro(): BelongsTo
    {
        return $this->belongsTo(Giro::class, 'GiroID', 'GiroID');
    }
}