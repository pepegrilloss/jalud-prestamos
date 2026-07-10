<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Excedente extends Model
{
    use HasFactory, BelongsToSede;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->ClienteOrigenID && $model->SedeID) {
                $clienteSedeID = \App\Models\Cliente::withoutGlobalScope('sede')
                    ->where('ClienteID', $model->ClienteOrigenID)
                    ->value('SedeID');
                if ($clienteSedeID && $clienteSedeID != $model->SedeID) {
                    \Illuminate\Support\Facades\Log::warning('Excedente ClienteOrigen cross-sede bloqueado', [
                        'ExcedenteID' => $model->ExcedenteID ?? 'new',
                        'Excedente.SedeID' => $model->SedeID,
                        'Cliente.SedeID' => $clienteSedeID,
                        'ClienteOrigenID' => $model->ClienteOrigenID,
                    ]);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'SedeID' => 'No se puede registrar un excedente con cliente origen de otra sede.',
                    ]);
                }
            }
            if ($model->PagoOrigenID && $model->SedeID) {
                $pagoSedeID = \App\Models\Pago::withoutGlobalScope('sede')
                    ->where('PagoID', $model->PagoOrigenID)
                    ->value('SedeID');
                if ($pagoSedeID && $pagoSedeID != $model->SedeID) {
                    \Illuminate\Support\Facades\Log::warning('Excedente PagoOrigen cross-sede bloqueado', [
                        'ExcedenteID' => $model->ExcedenteID ?? 'new',
                        'Excedente.SedeID' => $model->SedeID,
                        'Pago.SedeID' => $pagoSedeID,
                        'PagoOrigenID' => $model->PagoOrigenID,
                    ]);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'SedeID' => 'No se puede registrar un excedente con pago origen de otra sede.',
                    ]);
                }
            }
        });
    }

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
