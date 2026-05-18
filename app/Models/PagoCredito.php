<?php
// ============================================================
// app/Models/PagoCredito.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToSede;

class PagoCredito extends Model
{
    use BelongsToSede;

    protected $table = 'PagoCredito';
    protected $primaryKey = 'PagoCreditoID';
    public $timestamps = false;

    protected $fillable = [
        'CreditoID',
        'NumeroRecibo',
        'FechaPago',
        'MontoEfectivo',
        'MontoYape',
        'MontoTransferencia',
        'MontoTotal',
        'MontoAplicadoCapital',
        'MontoAplicadoInteres',
        'MontoAplicadoMora',
        'SaldoCapitalAnterior',
        'SaldoCapitalNuevo',
        'MetodoPago',
        'Observaciones',
        'UsuarioRegistro',
        'FechaRegistro',
        'UsuarioModificacion',
        'FechaModificacion',
        'Anulado',
        'MotivoAnulacion',
        'UsuarioAnulacion',
        'FechaAnulacion',
        'SedeID',
    ];

    protected $casts = [
        'FechaPago' => 'date',
        'FechaRegistro' => 'datetime',
        'FechaModificacion' => 'datetime',
        'FechaAnulacion' => 'datetime',
        'MontoEfectivo' => 'decimal:2',
        'MontoYape' => 'decimal:2',
        'MontoTransferencia' => 'decimal:2',
        'MontoTotal' => 'decimal:2',
        'MontoAplicadoCapital' => 'decimal:2',
        'MontoAplicadoInteres' => 'decimal:2',
        'MontoAplicadoMora' => 'decimal:2',
        'SaldoCapitalAnterior' => 'decimal:2',
        'SaldoCapitalNuevo' => 'decimal:2',
        'Anulado' => 'boolean',
    ];

    // Relaciones
    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'CreditoID', 'CreditoID');
    }

    // Boot method para manejar eventos
    protected static function boot()
    {
        parent::boot();

        // Al crear un pago
        static::creating(function ($pago) {
            $credito = Credito::find($pago->CreditoID);
            
            if (!$credito) {
                throw new \Exception('Crédito no encontrado');
            }

            if ($credito->EstadoCobranza !== 'ABIERTO') {
                throw new \Exception('El crédito está CERRADO para cobranza');
            }

            // Guardar saldo anterior
            $pago->SaldoCapitalAnterior = $credito->SaldoCapital;
            
            // Calcular nuevo saldo
            $nuevoSaldo = $credito->SaldoCapital - $pago->MontoTotal;
            $pago->SaldoCapitalNuevo = max($nuevoSaldo, 0);
            
            // El monto se aplica todo a capital por simplicidad
            $pago->MontoAplicadoCapital = $pago->MontoTotal;
            $pago->MontoAplicadoInteres = 0;
            $pago->MontoAplicadoMora = 0;
        });

        // Después de crear un pago
        static::created(function ($pago) {
            if (!$pago->Anulado) {
                $credito = Credito::find($pago->CreditoID);
                $credito->SaldoCapital = $pago->SaldoCapitalNuevo;
                
                // Si el saldo llega a 0, marcar como CANCELADO
                if ($credito->SaldoCapital <= 0) {
                    $credito->EstadoCredito = 'CANCELADO';
                }
                
                $credito->save();
            }
        });

        // Al actualizar (principalmente para anulación)
        static::updated(function ($pago) {
            if ($pago->Anulado && $pago->isDirty('Anulado')) {
                // Revertir el pago
                $credito = Credito::find($pago->CreditoID);
                $credito->SaldoCapital = $pago->SaldoCapitalAnterior;
                
                // Si estaba cancelado, volverlo a VIGENTE
                if ($credito->EstadoCredito === 'CANCELADO') {
                    $credito->EstadoCredito = 'VIGENTE';
                }
                
                $credito->save();
            }
        });
    }

    // Scopes
    public function scopeNoAnulados($query)
    {
        return $query->where('Anulado', false);
    }

    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('FechaPago', $fecha);
    }
}