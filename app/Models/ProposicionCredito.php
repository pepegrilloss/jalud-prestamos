<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\BelongsToSede;

class ProposicionCredito extends Model
{
    use BelongsToSede;
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
        'MontoTotalPagar',
        'SaldoPendiente',
        'TasaMora',
        'ZonaID',
        'CuentaParalela',
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
        'FechaCierre',
        'Activo',
        'EsRefinanciamiento',
        'ProposicionCreditoAnteriorID',
        'FueRefinanciada',
        'SedeID',
    ];

    protected $casts = [
        'MontoTotal' => 'decimal:2',
        'TasaInteres' => 'decimal:2',
        'MontoCuota' => 'decimal:2',
        'MontoInteres' => 'decimal:2',
        'MontoTotalPagar' => 'decimal:2',
        'TasaMora' => 'decimal:2',
        'FechaPropuesta' => 'datetime',
        'FechaAprobacion' => 'datetime',
        'FechaDesembolso' => 'datetime',
        'FechaModificacion' => 'datetime',
        'FechaCierre' => 'datetime',
        'Activo' => 'boolean',
        'EsRefinanciamiento' => 'boolean',
        'FueRefinanciada' => 'boolean',
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

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'ClienteID');
    }

    public function tipoCredito()
    {
        return $this->belongsTo(TipoCredito::class, 'TipoCreditoID');
    }

    public function tasa()
    {
        return $this->belongsTo(Tasa::class, 'TasaID');
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'ZonaID');
    }

    public function nivelAprobacion()
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionRequerido', 'NivelAprobacionID');
    }

    public function userProponente()
    {
        return $this->belongsTo(User::class, 'UserProponenteID');
    }

    public function userAprobador()
    {
        return $this->belongsTo(User::class, 'UserAprobadorID');
    }

    public function userDesembolso()
    {
        return $this->belongsTo(User::class, 'UserDesembolsoID');
    }

    public function userModificacion()
    {
        return $this->belongsTo(User::class, 'UserModificacionID');
    }

    public function aprobaciones()
    {
        return $this->hasMany(AprobacionProposicion::class, 'ProposicionCreditoID', 'ProposicionCreditoID');
    }

    public function credito()
    {
        return $this->hasOne(Credito::class, 'ProposicionCreditoID', 'ProposicionCreditoID');
    }

    public function proposicionAnterior()
    {
        return $this->belongsTo(ProposicionCredito::class, 'ProposicionCreditoAnteriorID', 'ProposicionCreditoID');
    }

    public function refinanciamientos()
    {
        return $this->hasMany(ProposicionCredito::class, 'ProposicionCreditoAnteriorID', 'ProposicionCreditoID');
    }

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
        $numero = ($ultimo ? (int) substr($ultimo->CodigoCredito, 2) + 1 : 1);
        return 'C-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Contar proposiciones activas (no aprobadas ni rechazadas) para un cliente
     */
    public static function contarProposicionesActivas($clienteID): int
    {
        return self::where('ClienteID', $clienteID)
            ->where('Activo', true)
            ->whereNotIn('Estado', ['APROBADO', 'RECHAZADO'])
            ->count();
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
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $this->FechaModificacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            $this->save();
        } elseif ($this->todasAprobacionesAprobadas()) {
            $ultimaAprobacion = $this->aprobaciones()->where('Estado', 'APROBADO')->latest('FechaAprobacion')->first();
            $this->Estado = 'APROBADO';
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $this->FechaAprobacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            $this->UserAprobadorID = $ultimaAprobacion?->UserAprobadorID;
            $this->FechaModificacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            $this->save();

            // Si es un refinanciamiento aprobado, desactivar y marcar como refinanciada la proposición anterior
            $this->desactivarProposicionRefinanciada();
        }
    }

    /**
     * Desactivar y marcar como refinanciada la proposición anterior si aplica
     */
    public function desactivarProposicionRefinanciada(): void
    {
        // Si tiene proposición anterior para refinanciar
        if ($this->ProposicionCreditoAnteriorID) {
            $proposicionAnterior = ProposicionCredito::find($this->ProposicionCreditoAnteriorID);

            if ($proposicionAnterior) {
                $proposicionAnterior->Activo = false;
                $proposicionAnterior->FueRefinanciada = true;
                $proposicionAnterior->FechaModificacion = now();
                $proposicionAnterior->save();
            }
        }
    }

    /**
     * Obtener todos los créditos activos con saldo pendiente para un cliente
     * Excluye créditos que fueron refinanciados (FueRefinanciada = 1)
     */
    public static function obtenerCreditosActivosConSaldo($clienteID)
    {
        return self::where('ClienteID', $clienteID)
            ->where('Activo', true)
            ->where('Estado', 'APROBADO')
            ->where('FueRefinanciada', 0)
            ->has('credito')
            ->with([
                'credito' => function ($query) {
                    $query->where('Activo', true);
                }
            ])
            ->get()
            ->filter(function ($proposicion) {
                return self::calcularSaldoPendiente($proposicion->ProposicionCreditoID) > 0;
            });
    }

    /**
     * Calcular el saldo pendiente de una proposición basado en sus cuotas
     */
    /**
     * Calcular el saldo pendiente de una proposición basado en sus cuotas
     * Usa la misma lógica que CreditoResource: Sum(MontoCuota) - Sum(MontoPagado)
     */
    public static function calcularSaldoPendiente($proposicionCreditoID)
    {
        $credito = Credito::where('ProposicionCreditoID', $proposicionCreditoID)
            ->where('Activo', true)
            ->first();

        if (!$credito) {
            return 0;
        }

        // Obtener proposición para el cálculo del total de cuotas
        $proposicion = ProposicionCredito::find($proposicionCreditoID);
        if (!$proposicion) {
            return 0;
        }

        // Calcular: Sum(MontoCuota) - Sum(MontoPagado desde tabla pago)
        // MontoCuota incluye Capital + Interés
        $montoCuotasTotal = (float) $credito->cuotas()
            ->where('Activo', true)
            ->sum('MontoCuota');

        // Calcular total pagado desde la tabla pago (no desde cuota)
        $totalPagado = \App\Models\Pago::where('Activo', 1)
            ->where(function ($q) {
                $q->whereNull('EstadoTraslado')
                  ->orWhere('EstadoTraslado', '!=', 'TRASLADADO');
            })
            ->whereHas('cuota', function ($query) use ($credito) {
                $query->where('CreditoID', $credito->CreditoID);
            })
            ->sum('MontoPagado');

        return max(0, $montoCuotasTotal - $totalPagado);
    }

    /**
     * Obtener información formateada de un crédito para el modal de refinanciamiento
     */
    public function obtenerInfoRefinanciamiento()
    {
        $saldoPendiente = self::calcularSaldoPendiente($this->ProposicionCreditoID);

        // Contar cuotas pendientes
        $credito = Credito::where('ProposicionCreditoID', $this->ProposicionCreditoID)
            ->where('Activo', true)
            ->first();

        $cuotasPendientes = 0;
        if ($credito) {
            $cuotasPendientes = $credito->cuotas()
                ->where('Activo', true)
                ->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA'])
                ->count();
        }

        return [
            'ProposicionCreditoID' => $this->ProposicionCreditoID,
            'CodigoCredito' => $this->CodigoCredito,
            'MontoOriginal' => (float) $this->MontoTotal,
            'SaldoPendiente' => (float) $saldoPendiente,
            'CuotasPendientes' => (int) $cuotasPendientes,
            'TasaInteres' => (float) $this->TasaInteres,
            'Plazo' => (int) $this->Plazo,
            'NumeroCuotas' => (int) $this->NumeroCuotas,
            'TasaMora' => (float) $this->TasaMora,
            'TipoCreditoID' => $this->TipoCreditoID,
            'TasaID' => $this->TasaID,
        ];
    }
}