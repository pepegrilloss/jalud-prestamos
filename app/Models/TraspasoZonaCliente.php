<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class TraspasoZonaCliente extends Model
{
    use BelongsToSede;

    protected $table = 'traspaso_zona_clientes';

    protected $fillable = [
        'ClienteID',
        'ZonaAnteriorID',
        'ZonaNuevaID',
        'PromotorAnteriorID',
        'PromotorNuevoID',
        'MotivoTraspaso',
        'UserSolicitaID',
        'SedeID',
        'FechaTraspaso',
    ];

    protected $casts = [
        'FechaTraspaso' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // --- Relaciones ---

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID');
    }

    public function zonaAnterior(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'ZonaAnteriorID', 'ZonaID');
    }

    public function zonaNueva(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'ZonaNuevaID', 'ZonaID');
    }

    public function promotorAnterior(): BelongsTo
    {
        return $this->belongsTo(PromotorCobrador::class, 'PromotorAnteriorID', 'PromotorCobradorID');
    }

    public function promotorNuevo(): BelongsTo
    {
        return $this->belongsTo(PromotorCobrador::class, 'PromotorNuevoID', 'PromotorCobradorID');
    }

    public function userSolicita(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserSolicitaID');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }
}
