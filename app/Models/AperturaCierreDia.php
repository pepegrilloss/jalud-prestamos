<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AperturaCierreDia extends Model
{
    protected $table = 'apertura_cierre_dia';
    protected $primaryKey = 'AperturaCierreDiaID';

    protected $fillable = [
        'Fecha',
        'FechaApertura',
        'FechaCierre',
        'EstadoDia',
        'UsuarioAperturaID',
        'UsuarioCierreID',
        'Observaciones',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'FechaApertura' => 'datetime',
        'FechaCierre' => 'datetime',
    ];

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioAperturaID', 'id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioCierreID', 'id');
    }

    /**
     * Obtiene el registro de apertura/cierre del día actual
     */
    public static function hoyOHoy(): ?self
    {
        return self::whereDate('Fecha', today())->first();
    }

    /**
     * Verifica si el día actual está abierto
     */
    public static function estaAbierto(): bool
    {
        $hoy = self::hoyOHoy();
        return $hoy && $hoy->EstadoDia === 'ABIERTO';
    }

    /**
     * Obtiene el estado del día actual
     */
    public static function estadoDiaActual(): string
    {
        $hoy = self::hoyOHoy();
        return $hoy?->EstadoDia ?? 'CERRADO';
    }
}
