<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoFondo extends Model
{
    use HasFactory;

    protected $table = 'movimientos_fondo';
    protected $primaryKey = 'MovimientoID';

    protected $fillable = [
        'SedeID',
        'Tipo',
        'Monto',
        'SaldoAnterior',
        'SaldoNuevo',
        'TransferenciaID',
        'UsuarioID',
        'Observacion',
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }

    public function transferencia()
    {
        return $this->belongsTo(TransferenciaSede::class, 'TransferenciaID', 'TransferenciaID');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'UsuarioID');
    }
}
