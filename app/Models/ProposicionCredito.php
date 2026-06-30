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
            ->orderBy('Orden', 'desc')
            ->first();
    }

    public static function generarCodigoCredito()
    {
        $ultimo = self::withoutGlobalScope('sede')
            ->where('CodigoCredito', 'like', 'C-%')
            ->orderByRaw('CAST(SUBSTRING(CodigoCredito, 3) AS UNSIGNED) DESC')
            ->value('CodigoCredito');
        $numero = $ultimo ? (int) substr($ultimo, 2) + 1 : 1;
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
        $nivel = self::obtenerNivelAprobacionRequerido($this->MontoTotal);

        if (!$nivel) {
            $nivel = NivelAprobacion::where('Activo', true)
                ->orderBy('Orden', 'asc')
                ->first();
        }

        if (!$nivel) {
            return;
        }

        $aprobacionPendiente = $this->aprobaciones()
            ->where('Estado', 'PENDIENTE')
            ->first();

        if ($aprobacionPendiente) {
            if ($aprobacionPendiente->NivelAprobacionID !== $nivel->NivelAprobacionID) {
                $aprobacionPendiente->NivelAprobacionID = $nivel->NivelAprobacionID;
                $aprobacionPendiente->save();
            }
        } elseif (!$this->aprobaciones()->exists()) {
            AprobacionProposicion::create([
                'ProposicionCreditoID' => $this->ProposicionCreditoID,
                'NivelAprobacionID' => $nivel->NivelAprobacionID,
                'Estado' => 'PENDIENTE',
            ]);
        }

        $this->NivelAprobacionRequerido = $nivel->NivelAprobacionID;
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

            $this->notificarCambioEstado('RECHAZADO');
        } elseif ($this->todasAprobacionesAprobadas()) {
            $ultimaAprobacion = $this->aprobaciones()->where('Estado', 'APROBADO')->latest('FechaAprobacion')->first();
            $this->Estado = 'APROBADO';
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $this->FechaAprobacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            $this->UserAprobadorID = $ultimaAprobacion?->UserAprobadorID;
            $this->FechaModificacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            $this->save();

            $this->desactivarProposicionRefinanciada();
            $this->notificarCambioEstado('APROBADO');
        }
    }

    /**
     * Desactivar y marcar como refinanciada la proposición anterior si aplica
     */
    public function desactivarProposicionRefinanciada(): void
    {
        if ($this->ProposicionCreditoAnteriorID) {
            $proposicionAnterior = ProposicionCredito::find($this->ProposicionCreditoAnteriorID);

            if ($proposicionAnterior) {
                $proposicionAnterior->Activo = false;
                $proposicionAnterior->FueRefinanciada = true;
                $proposicionAnterior->SaldoPendiente = 0;
                $proposicionAnterior->FechaModificacion = now();
                $proposicionAnterior->save();

                // Marcar el credito anterior como SALDADO
                $creditoAnterior = \App\Models\Credito::where('ProposicionCreditoID', $proposicionAnterior->ProposicionCreditoID)
                    ->where('Activo', 1)
                    ->first();
                if ($creditoAnterior && $creditoAnterior->EstatusCreditoFinal !== 'SALDADO') {
                    $creditoAnterior->EstatusCreditoFinal = 'SALDADO';
                    $creditoAnterior->FechaSaldamiento = now();
                    $creditoAnterior->save();
                }
            }
        }
    }

    private function notificarCambioEstado(string $estado): void
    {
        try {
            $cliente = $this->cliente;
            $nombre = $cliente?->NombresApellidos ?? 'N/A';
            $monto = number_format((float) $this->MontoTotal, 2);
            $codigo = $this->CodigoCredito;
            $icono = $estado === 'APROBADO' ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle';

            \App\Models\User::notificarAdmin(
                "Proposición {$estado}",
                "{$codigo} — {$nombre} — S/ {$monto}",
                $icono,
                $this->SedeID
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * Obtener todos los créditos activos con saldo pendiente para un cliente
     * Excluye créditos que fueron refinanciados (FueRefinanciada = 1)
     * OPTIMIZADO: Filtra por columna SaldoPendiente en vez de recalcular en loop
     */
    public static function obtenerCreditosActivosConSaldo($clienteID)
    {
        return self::where('ClienteID', $clienteID)
            ->where('Activo', true)
            ->where('Estado', 'APROBADO')
            ->where('FueRefinanciada', 0)
            ->where('SaldoPendiente', '>', 0)
            ->has('credito')
            ->with([
                'credito' => function ($query) {
                    $query->where('Activo', true);
                }
            ])
            ->get();
    }

    /**
     * Obtener el saldo pendiente de una proposición.
     * Lee directamente de la columna SaldoPendiente (0 queries adicionales).
     * La columna se mantiene sincronizada por el PagoObserver.
     */
    public static function calcularSaldoPendiente($proposicionCreditoID)
    {
        return \App\Services\SaldoPendienteService::obtener($proposicionCreditoID);
    }

    /**
     * Recalcular y guardar el saldo pendiente (solo para operaciones de escritura).
     */
    public static function recalcularSaldoPendiente($proposicionCreditoID): float
    {
        return \App\Services\SaldoPendienteService::recalcular($proposicionCreditoID);
    }

    /**
     * Obtener información formateada de un crédito para el modal de refinanciamiento
     * OPTIMIZADO: Lee SaldoPendiente de la columna
     */
    public function obtenerInfoRefinanciamiento()
    {
        $saldoPendiente = (float) ($this->SaldoPendiente ?? 0);

        // Contar cuotas pendientes
        $credito = Credito::withoutEagerLoads()
            ->where('ProposicionCreditoID', $this->ProposicionCreditoID)
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
            'SaldoPendiente' => $saldoPendiente,
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