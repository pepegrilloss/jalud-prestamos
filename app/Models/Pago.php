<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Pago extends Model
{
    use BelongsToSede;
    protected $table = 'pago';
    protected $primaryKey = 'PagoID';
    public $timestamps = false;

    protected $fillable = [
        'CreditoID',
        'CuotaID',
        'PromotorCobradorID',
        'MontoPagado',
        'FechaPago',
        'TipoPago',
        'TipoConcepto',
        'EsMora',
        'EsPagoAMayor',
        'EsPagoAMayorPorMora',
        'EsPagoInicial',
        'EsPagoForzado',
        'EsPagoAutomatico',
        'Comentario',
        'UsuarioRegistro',
        'UserModificacionID',
        'FechaModificacion',
        'FechaCreacion',
        'Activo',
        'SedeID',
        'SolicitudResolucionID',
        'PagoOrigenID',
        'EstadoTraslado',
    ];

    protected $casts = [
        'FechaPago' => 'datetime:Y-m-d',
        'FechaModificacion' => 'datetime',
        'FechaCierre' => 'datetime',
        'EsMora' => 'boolean',
        'EsPagoAMayor' => 'boolean',
        'EsPagoAMayorPorMora' => 'boolean',
        'EsPagoInicial' => 'boolean',
        'EsPagoForzado' => 'boolean',
        'EsPagoAutomatico' => 'boolean',
        'Activo' => 'boolean'
    ];

    // Relaciones
    public function credito()
    {
        return $this->belongsTo(Credito::class, 'CreditoID', 'CreditoID');
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class, 'CuotaID', 'CuotaID');
    }

    public function promotorCobrador()
    {
        return $this->belongsTo(PromotorCobrador::class, 'PromotorCobradorID', 'PromotorCobradorID');
    }

    public function solicitudResolucion()
    {
        return $this->belongsTo(SolicitudResolucionExcedente::class, 'SolicitudResolucionID', 'SolicitudID');
    }

    public function pagoOrigen()
    {
        return $this->belongsTo(Pago::class, 'PagoOrigenID', 'PagoID');
    }

    // Tipos de pago
    const TIPO_EFECTIVO = 'EFECTIVO';
    const TIPO_YAPE = 'YAPE';
    const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';

    public static function getTiposPago()
    {
        return [
            self::TIPO_EFECTIVO => 'Efectivo',
            self::TIPO_YAPE => 'Yape',
            self::TIPO_TRANSFERENCIA => 'Transferencia'
        ];
    }
}
