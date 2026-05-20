<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferenciaSede extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                $model->FechaTransferencia = $fecha;
                $model->created_at = $fecha;
                $model->updated_at = $fecha;
            }
        });

        static::updating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                if ($model->isDirty('FechaRespuesta')) {
                    $model->FechaRespuesta = $fecha;
                }
                $model->updated_at = $fecha;
            }
        });
    }

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
