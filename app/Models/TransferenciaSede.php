<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TransferenciaSede extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('sede', function (Builder $query) {
            if (!auth()->check()) {
                return;
            }

            // Gerencia ve TODAS las transferencias sin filtro de sede
            if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
                return;
            }

            $user = auth()->user();
            $esPrivilegiado = $user->isPrivileged();
            $sedeActiva = session('sede_activa');
            $sedeUsuario = $user->SedeID;

            $sedeId = null;

            if ($esPrivilegiado) {
                if ($sedeActiva) {
                    $sedeId = $sedeActiva;
                } else {
                    $query->whereRaw('1 = 0');
                    return;
                }
            } else {
                $sedeId = $sedeUsuario;
            }

            if ($sedeId) {
                $query->where(function ($q) use ($sedeId) {
                    $q->where('SedeOrigenID', $sedeId)
                      ->orWhere('SedeDestinoID', $sedeId);
                });
            }
        });

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
        'VoucherImagen',
        'FechaCierre',
    ];

    protected function setVoucherImagenAttribute($value)
    {
        if ($value && is_string($value) && str_contains($value, '.')) {
            $rutaCompleta = \Illuminate\Support\Facades\Storage::disk('public')->path($value);
            $resultado = \App\Helpers\ImageOptimizer::optimize($rutaCompleta);
            $rutaFinal = $resultado['path'];
            $rutaFinal = str_replace(\Illuminate\Support\Facades\Storage::disk('public')->path(''), '', $rutaFinal);
            $this->attributes['VoucherImagen'] = str_replace('\\', '/', ltrim($rutaFinal, '\\/'));
        } else {
            $this->attributes['VoucherImagen'] = $value;
        }
    }

    protected $casts = [
        'Monto' => 'decimal:2',
        'MontoAprobado' => 'decimal:2',
        'EsSolicitudCapital' => 'boolean',
        'EsSolicitudGerencia' => 'boolean',
        'FechaTransferencia' => 'datetime',
        'FechaRespuesta' => 'datetime',
        'FechaCierre' => 'datetime',
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
