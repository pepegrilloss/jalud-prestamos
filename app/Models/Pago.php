<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pago';
    protected $primaryKey = 'PagoID';
    public $timestamps = false;

    protected $fillable = [
        'CreditoID',
        'CuotaID',
        'PromotorCobradorID',
        'MontoPagado',
        'FechaPago',
        'EsMora',
        'EsPagoAMayor',
        'EsPagoForzado',
        'Comentario',
        'UsuarioRegistro',
        'Activo'
    ];

    protected $casts = [
        'FechaPago' => 'datetime:Y-m-d',
        'EsMora' => 'boolean',
        'EsPagoAMayor' => 'boolean',
        'EsPagoForzado' => 'boolean',
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
