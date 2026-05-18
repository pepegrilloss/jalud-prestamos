<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToSede;

class Negocio extends Model
{
    use BelongsToSede;
    protected $table = 'Negocio';
    protected $primaryKey = 'NegocioID';
    public $timestamps = false;

    protected $fillable = [
        'ClienteID',
        'CiudadID',
        'ZonaID',
        'DireccionNegocio',
        'Antiguedad',
        'GiroID',
        'SubGiroID',
        'ObservacionGiro',
        'Ubicacion',
        'Calificacion',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
        'Antiguedad' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID');
    }

    public function giro(): BelongsTo
    {
        return $this->belongsTo(Giro::class, 'GiroID', 'GiroID');
    }

    public function subGiro(): BelongsTo
    {
        return $this->belongsTo(SubGiro::class, 'SubGiroID', 'SubGiroID');
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'CiudadID', 'CiudadID');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'ZonaID', 'ZonaID');
    }

    public function telefonos(): HasMany
    {
        return $this->hasMany(TelefonoNegocio::class, 'NegocioID', 'NegocioID');
    }
}