<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuotaPrestamoBancario extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_CANCELADA = 'CANCELADA';

    public const ESTADO_ANULADA_ANTICIPADA = 'ANULADA_ANTICIPADA';

    protected $table = 'tesoreria_prestamo_cuotas';

    protected $primaryKey = 'CuotaPrestamoBancarioID';

    protected $fillable = [
        'PrestamoBancarioID', 'Numero', 'FechaVencimiento', 'Capital', 'Interes', 'Comision',
        'Seguros', 'MontoCuota', 'SaldoDeuda', 'Estado', 'FechaPago',
    ];

    protected $casts = [
        'FechaVencimiento' => 'date', 'FechaPago' => 'date', 'Capital' => 'decimal:2',
        'Interes' => 'decimal:2', 'Comision' => 'decimal:2', 'Seguros' => 'decimal:2',
        'MontoCuota' => 'decimal:2', 'SaldoDeuda' => 'decimal:2',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoBancario::class, 'PrestamoBancarioID', 'PrestamoBancarioID');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoPrestamoBancario::class, 'CuotaPrestamoBancarioID', 'CuotaPrestamoBancarioID');
    }
}
