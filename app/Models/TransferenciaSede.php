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
        'UsuarioOrigenID',
        'UsuarioRespondeID',
        'Monto',
        'Estado',
        'Observacion',
        'FechaTransferencia',
        'FechaRespuesta',
    ];

    protected $casts = [
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
