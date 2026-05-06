<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Excedente extends Model
{
    use HasFactory, BelongsToSede;

    protected $table = 'excedentes';
    protected $primaryKey = 'ExcedenteID';

    protected $fillable = [
        'TipoExcedente',
        'NroOperacion',
        'Monto',
        'Fecha',
        'Hora',
        'Observaciones',
        'VoucherImagen',
        'Activo',
        'ZonaID',
        'SedeID',
        'ClienteOrigenID',
        'PagoOrigenID',
        'EstadoResolucion',
        'Cuenta',
        'FechaCierre',
        'UsuarioRegistro',
        'UsuarioModificacion',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'Fecha' => 'date',
        'Monto' => 'decimal:2',
    ];

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'ZonaID', 'ZonaID');
    }

    public function clienteOrigen()
    {
        return $this->belongsTo(Cliente::class, 'ClienteOrigenID', 'ClienteID');
    }

    public function pagoOrigen()
    {
        return $this->belongsTo(Pago::class, 'PagoOrigenID', 'PagoID');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'UsuarioRegistro', 'id');
    }

    public function usuarioModificacion()
    {
        return $this->belongsTo(User::class, 'UsuarioModificacion', 'id');
    }

    public function resoluciones()
    {
        return $this->hasMany(SolicitudResolucionExcedente::class, 'ExcedenteID', 'ExcedenteID');
    }
}
