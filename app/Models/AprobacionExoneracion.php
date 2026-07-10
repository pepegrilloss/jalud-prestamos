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

    protected static function booted(): void
    {
        static::saving(function (AprobacionExoneracion $aprobacion) {
            if (!$aprobacion->SolicitudExoneracionID) {
                return;
            }

            $solicitudSedeId = SolicitudExoneracion::withoutGlobalScope('sede')
                ->where('SolicitudExoneracionID', $aprobacion->SolicitudExoneracionID)
                ->value('SedeID');

            if (!$solicitudSedeId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'SolicitudExoneracionID' => 'No se encontro la solicitud de exoneracion.',
                ]);
            }

            if (empty($aprobacion->SedeID)) {
                $aprobacion->SedeID = $solicitudSedeId;
            }

            if ((int) $aprobacion->SedeID !== (int) $solicitudSedeId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'SedeID' => 'No se puede guardar una aprobacion de exoneracion en una sede distinta a su solicitud.',
                ]);
            }
        });
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudExoneracion::class, 'SolicitudExoneracionID');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionID');
    }
}
