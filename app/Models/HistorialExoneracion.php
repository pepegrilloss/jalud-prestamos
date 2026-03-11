<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class HistorialExoneracion extends Model
{
    use BelongsToSede;
    protected $table = 'HistorialExoneracion';
    protected $primaryKey = 'HistorialExoneracionID';
    public $timestamps = false;
    const CREATED_AT = 'FechaExoneracion';
    const UPDATED_AT = null;

    protected $fillable = [
        'SolicitudExoneracionID',
        'CreditoID',
        'ClienteID',
        'TipoExoneracion',
        'MontoExonerado',
        'UsuarioAprobador',
        'Comentario',
        'SedeID',
    ];

    protected $casts = [
        'MontoExonerado' => 'decimal:2',
        'FechaExoneracion' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudExoneracion::class, 'SolicitudExoneracionID');
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'CreditoID');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ClienteID');
    }
}
