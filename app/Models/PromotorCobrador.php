<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class PromotorCobrador extends Model
{
    use BelongsToSede;
    protected $table = 'PromotorCobrador';
    protected $primaryKey = 'PromotorCobradorID';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Descripcion',
        'CiudadID',
        'ZonaID',
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'ZonaID', 'ZonaID');
    }
}