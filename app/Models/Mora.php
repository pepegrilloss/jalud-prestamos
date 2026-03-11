<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class Mora extends Model
{
    use BelongsToSede;
    protected $table = 'mora';
    protected $primaryKey = 'MoraID';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'CreditoID',
        'FechaMora',
        'SaldoPendiente',
        'PorcentajeMora',
        'MontoMora',
        'MoraAcumulada',
        'SedeID',
    ];

    protected $casts = [
        'FechaMora' => 'date',
        'SaldoPendiente' => 'decimal:2',
        'PorcentajeMora' => 'decimal:2',
        'MontoMora' => 'decimal:2',
        'MoraAcumulada' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relación con Crédito
    public function credito()
    {
        return $this->belongsTo(Credito::class, 'CreditoID', 'CreditoID');
    }

    // Obtener la mora más reciente de un crédito
    public static function getMoraActual($creditoId)
    {
        return self::where('CreditoID', $creditoId)
            ->orderBy('FechaMora', 'desc')
            ->first();
    }
}
