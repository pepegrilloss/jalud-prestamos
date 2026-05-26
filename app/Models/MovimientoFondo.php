<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class MovimientoFondo extends Model
{
    use HasFactory, BelongsToSede;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                $model->created_at = $fecha;
                $model->updated_at = $fecha;
            }
        });

        static::updating(function ($model) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $fecha = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
                $model->updated_at = $fecha;
            }
        });
    }

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
        'VoucherImagen',
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
