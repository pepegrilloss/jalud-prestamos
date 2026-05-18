<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class TelefonoNegocio extends Model
{
    use BelongsToSede;
    protected $table = 'TelefonoNegocio';
    protected $primaryKey = 'TelefonoNegocioID';
    public $timestamps = false;

    protected $fillable = [
        'NegocioID',
        'Telefono',
        'TipoTelefono',
        'Observacion',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'NegocioID', 'NegocioID');
    }
}