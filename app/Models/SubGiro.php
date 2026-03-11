<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class SubGiro extends Model
{
    use BelongsToSede;
    protected $table = 'SubGiro';
    protected $primaryKey = 'SubGiroID';
    public $timestamps = false;

    protected $fillable = [
        'GiroID',
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

    public function giro(): BelongsTo
    {
        return $this->belongsTo(Giro::class, 'GiroID', 'GiroID');
    }
}