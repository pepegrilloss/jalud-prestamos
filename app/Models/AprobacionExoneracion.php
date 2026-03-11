<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class AprobacionExoneracion extends Model
{
    use BelongsToSede;
    protected $table = 'AprobacionExoneracion';
    protected $primaryKey = 'AprobacionExoneracionID';
    public $timestamps = false;
    const CREATED_AT = 'FechaCreacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'SolicitudExoneracionID',
        'NivelAprobacionID',
        'UserAprobadorID',
        'Estado',
        'Comentario',
        'FechaAprobacion',
        'SedeID',
    ];

    protected $casts = [
        'FechaAprobacion' => 'datetime',
        'FechaCreacion' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudExoneracion::class, 'SolicitudExoneracionID');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionID');
    }
}
