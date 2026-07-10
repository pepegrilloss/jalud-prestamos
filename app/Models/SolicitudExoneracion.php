<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

class SolicitudExoneracion extends Model
{
    use BelongsToSede;
    protected $table = 'SolicitudExoneracion';
    protected $primaryKey = 'SolicitudExoneracionID';
    public $timestamps = true;
    const CREATED_AT = 'FechaSolicitud';
    const UPDATED_AT = 'FechaModificacion';

    protected $fillable = [
        'CreditoID',
        'TipoExoneracionID',
        'MontoDisponible',
        'MontoExonerado',
        'Comentario',
        'Estado',
        'UserSolicitanteID',
        'NivelAprobacionRequerido',
        'UserAprobadorID',
        'FechaAprobacion',
        'ComentarioAprobacion',
        'PagoGeneradoID',
        'UserModificacionID',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'MontoDisponible' => 'decimal:2',
        'MontoExonerado' => 'decimal:2',
        'FechaSolicitud' => 'datetime',
        'FechaAprobacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SolicitudExoneracion $solicitud) {
            if (!$solicitud->CreditoID) {
                return;
            }

            $credito = Credito::withoutGlobalScope('sede')
                ->where('CreditoID', $solicitud->CreditoID)
                ->first();

            if (!$credito) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'CreditoID' => 'No se encontro el credito de la solicitud de exoneracion.',
                ]);
            }

            if (empty($solicitud->SedeID)) {
                $solicitud->SedeID = $credito->SedeID;
            }

            app(\App\Services\SedeIntegrityService::class)->assertRecordSede($credito, (int) $solicitud->SedeID, 'credito de la solicitud de exoneracion');

            if ($solicitud->PagoGeneradoID) {
                app(\App\Services\SedeIntegrityService::class)->assertIdSede(
                    Pago::class,
                    'PagoID',
                    (int) $solicitud->PagoGeneradoID,
                    (int) $solicitud->SedeID,
                    'pago generado por exoneracion'
                );
            }
        });
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'CreditoID');
    }

    public function tipoExoneracion(): BelongsTo
    {
        return $this->belongsTo(TipoExoneracion::class, 'TipoExoneracionID');
    }

    public function nivelAprobacion(): BelongsTo
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionRequerido');
    }

    public function aprobaciones(): HasMany
    {
        return $this->hasMany(AprobacionExoneracion::class, 'SolicitudExoneracionID');
    }

    public function pagoGenerado(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'PagoGeneradoID');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialExoneracion::class, 'SolicitudExoneracionID');
    }
}
