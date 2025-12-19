<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisEconomico extends Model
{
    protected $table = 'AnalisisEconomico';
    protected $primaryKey = 'AnalisisEconomicoID';
    public $timestamps = false;

    protected $fillable = [
        'ClienteID',
        'CapitalManifestado',
        'CapitalEstimado',
        'VentaManifestadaMin',
        'VentaManifestadaMax',
        'VentaEstimada',
        'FechaAnalisis',
        'UsuarioAnalisis',
        'UsuarioModificacion',
        'Activo',
    ];

    protected $casts = [
        'CapitalManifestado' => 'decimal:2',
        'CapitalEstimado' => 'decimal:2',
        'VentaManifestadaMin' => 'decimal:2',
        'VentaManifestadaMax' => 'decimal:2',
        'VentaEstimada' => 'decimal:2',
        'FechaAnalisis' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID');
    }
}