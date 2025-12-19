<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProposicionCredito extends Model
{
    protected $table = 'ProposicionCredito';
    protected $primaryKey = 'ProposicionCreditoID';
    
    // IMPORTANTE para SQL Server: Indicar que es autoincremental
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'CodigoCredito',
        'ClienteID',
        'CodigoCliente',
        'TipoCreditoID',
        'MontoTotal',
        'TasaID',
        'TasaInteres',
        'Plazo',
        'NumeroCuotas',
        'MontoCuota',
        'MontoInteres',
        'TasaMora',
        'ZonaID',
        'Observaciones',
        'UserProponenteID',
        'FechaPropuesta',
        'Estado',
        'NivelAprobacionRequerido',
        'UserAprobadorID',
        'FechaAprobacion',
        'ComentarioAprobacion',
        'FechaDesembolso',
        'UserDesembolsoID',
        'FechaModificacion',
        'UserModificacionID',
        'Activo',
    ];

    protected $casts = [
        'MontoTotal' => 'decimal:2',
        'TasaInteres' => 'decimal:2',
        'MontoCuota' => 'decimal:2',
        'MontoInteres' => 'decimal:2',
        'TasaMora' => 'decimal:2',
        'FechaPropuesta' => 'datetime',
        'FechaAprobacion' => 'datetime',
        'FechaDesembolso' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
    ];

    /**
     * FIX PARA SQL SERVER: Evitar actualización de columna IDENTITY
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            // Eliminamos la llave primaria de los atributos a actualizar
            // Esto evita el error SQLSTATE[42000] en SQL Server
            unset($model->{$model->getKeyName()});
        });
    }

    // --- Relaciones ---

    public function cliente() { return $this->belongsTo(Cliente::class, 'ClienteID'); }
    public function tipoCredito() { return $this->belongsTo(TipoCredito::class, 'TipoCreditoID'); }
    public function tasa() { return $this->belongsTo(Tasa::class, 'TasaID'); }
    public function zona() { return $this->belongsTo(Zona::class, 'ZonaID'); }
    public function nivelAprobacion() { return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionRequerido', 'NivelAprobacionID'); }
    public function userProponente() { return $this->belongsTo(User::class, 'UserProponenteID'); }
    public function userAprobador() { return $this->belongsTo(User::class, 'UserAprobadorID'); }
    public function userDesembolso() { return $this->belongsTo(User::class, 'UserDesembolsoID'); }
    public function userModificacion() { return $this->belongsTo(User::class, 'UserModificacionID'); }
    public function aprobaciones() { return $this->hasMany(AprobacionProposicion::class, 'ProposicionCreditoID', 'ProposicionCreditoID'); }
    public function credito() { return $this->hasOne(Credito::class, 'ProposicionCreditoID', 'ProposicionCreditoID'); }

    // --- Métodos de Lógica ---

    public static function obtenerNivelAprobacionRequerido($monto)
    {
        return NivelAprobacion::where('Activo', true)
            ->where('MontoMinimo', '<=', $monto)
            ->where('MontoMaximo', '>=', $monto)
            ->first();
    }

    public static function generarCodigoCredito()
    {
        $ultimo = self::orderBy('ProposicionCreditoID', 'desc')->first();
        $numero = ($ultimo ? (int)substr($ultimo->CodigoCredito, 2) + 1 : 1);
        return 'C-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    public function crearAprobacionesRequeridas(): void
    {
        $nivelesACrear = [];
        $nivelMasAlto = null;

        if ($this->MontoTotal <= 5000) {
            $nivelesACrear = [3]; // Jefe de Oficina
        } elseif ($this->MontoTotal <= 30000) {
            $nivelesACrear = [3, 2]; // Jefe de Oficina + Supervisor Operativo
        } else {
            $nivelesACrear = [3, 2, 1]; // Jefe de Oficina + Supervisor Operativo + Gerencia
        }

        $niveles = NivelAprobacion::where('Activo', true)
            ->whereIn('Orden', $nivelesACrear)
            ->orderBy('Orden', 'asc')
            ->get();

        foreach ($niveles as $nivel) {
            AprobacionProposicion::firstOrCreate(
                [
                    'ProposicionCreditoID' => $this->ProposicionCreditoID,
                    'NivelAprobacionID' => $nivel->NivelAprobacionID,
                ],
                ['Estado' => 'PENDIENTE']
            );
            $nivelMasAlto = $nivel->NivelAprobacionID;
        }

        // Al usar el boot anterior, este save() ya no fallará
        $this->NivelAprobacionRequerido = $nivelMasAlto;
        $this->save(); 
    }

    public function obtenerProximaAprobacionPendiente(): ?AprobacionProposicion
    {
        return $this->aprobaciones()
            ->where('Estado', 'PENDIENTE')
            ->orderBy('NivelAprobacionID', 'asc')
            ->first();
    }


    public function puedeAprobarEstaNivel(AprobacionProposicion $aprobacion): bool
    {
        $proximaPendiente = $this->obtenerProximaAprobacionPendiente();
        if (!$proximaPendiente) {
            return false;
        }
        return $proximaPendiente->AprobacionProposicionID === $aprobacion->AprobacionProposicionID;
    }

    public function hayRechazo(): bool
    {
        return $this->aprobaciones()->where('Estado', 'RECHAZADO')->exists();
    }

    public function todasAprobacionesAprobadas(): bool
    {
        $total = $this->aprobaciones()->count();
        $aprobadas = $this->aprobaciones()->where('Estado', 'APROBADO')->count();
        return $total > 0 && $total === $aprobadas;
    }

    public function actualizarEstadoSegunAprobaciones(): void
    {
        if ($this->hayRechazo()) {
            $this->Estado = 'RECHAZADO';
            $this->FechaModificacion = now();
            $this->save();
        } elseif ($this->todasAprobacionesAprobadas()) {
            $ultimaAprobacion = $this->aprobaciones()->where('Estado', 'APROBADO')->latest('FechaAprobacion')->first();
            $this->Estado = 'APROBADO';
            $this->FechaAprobacion = now();
            $this->UserAprobadorID = $ultimaAprobacion?->UserAprobadorID;
            $this->FechaModificacion = now();
            $this->save();
        }
    }
}