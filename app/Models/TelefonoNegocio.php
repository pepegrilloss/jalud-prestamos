<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\EncryptionHelper;

class TelefonoNegocio extends Model
{
    protected $table = 'TelefonoNegocio';
    protected $primaryKey = 'TelefonoNegocioID';
    public $timestamps = false;

    protected $fillable = [
        'NegocioID',
        'Telefono',
        'TipoTelefono',
        'Observacion',
        'Activo',
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    /**
     * SEGURIDAD: Teléfono encriptado en BD
     */
    protected function setTelefonoAttribute($value)
    {
        $this->attributes['Telefono'] = EncryptionHelper::encryptIfNeeded($value);
    }

    protected function getTelefonoAttribute($value)
    {
        return EncryptionHelper::decrypt($value);
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class, 'NegocioID', 'NegocioID');
    }
}