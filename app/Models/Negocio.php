<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Negocio extends Model
{
    protected $table = 'Negocio';
    protected $primaryKey = 'NegocioID';
    public $timestamps = false;

    protected $fillable = [
        'ClienteID',
        'DireccionNegocio',
        'Antiguedad',
        'GiroID',
        'SubGiroID',
        'Ubicacion',        // NUEVO
        'Mantenimiento',    // NUEVO
        'Activo',
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

    public function telefonos(): HasMany
    {
        return $this->hasMany(TelefonoNegocio::class, 'NegocioID', 'NegocioID');
    }
}