<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferenciaSede extends Model
{
    use HasFactory;

    protected $table = 'transferencia_sedes';
    protected $primaryKey = 'TransferenciaID';

    protected $fillable = [
        'SedeOrigenID',
        'SedeDestinoID',
        'CuentaOrigen',
        'CuentaDestino',
            'EsSolicitudCapital',
            'EsSolicitudGerencia',
        'UsuarioOrigenID',
        'UsuarioRespondeID',
        'Monto',
        'MontoAprobado',
        'Estado',
        'Observacion',
        'FechaTransferencia',
        'FechaRespuesta',
    ];

    protected $casts = [
        'Monto' => 'decimal:2',
        'MontoAprobado' => 'decimal:2',
        'EsSolicitudCapital' => 'boolean',
        'EsSolicitudGerencia' => 'boolean',
        'FechaTransferencia' => 'datetime',
        'FechaRespuesta' => 'datetime',
    ];

    public function sedeOrigen()
    {
        return $this->belongsTo(Sede::class, 'SedeOrigenID', 'SedeID');
    }

    public function sedeDestino()
    {
        return $this->belongsTo(Sede::class, 'SedeDestinoID', 'SedeID');
    }

    public function usuarioOrigen()
    {
        return $this->belongsTo(User::class, 'UsuarioOrigenID');
    }

    public function usuarioResponde()
    {
        return $this->belongsTo(User::class, 'UsuarioRespondeID');
    }
}
