<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudExoneracion extends Model
{
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
    ];

    protected $casts = [
        'MontoDisponible' => 'decimal:2',
        'MontoExonerado' => 'decimal:2',
        'FechaSolicitud' => 'datetime',
        'FechaAprobacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
    ];

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
