<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use App\Traits\ResolvesRosCasoSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosOperacion extends Model
{
    use BelongsToSede, ResolvesRosCasoSede;

    protected $table = 'ros_operaciones';
    protected $primaryKey = 'RosOperacionID';
    protected $fillable = [
        'RosCasoID', 'SedeID', 'ClienteID', 'CreditoID', 'PagoID', 'ProductoServicio',
        'CodigoProducto', 'NumeroOperacion', 'Monto', 'Moneda', 'FechaOperacion', 'Detalle',
    ];
    protected $casts = ['Monto' => 'decimal:2', 'FechaOperacion' => 'date'];

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID'); }
    public function credito(): BelongsTo { return $this->belongsTo(Credito::class, 'CreditoID', 'CreditoID'); }
    public function pago(): BelongsTo { return $this->belongsTo(Pago::class, 'PagoID', 'PagoID'); }
}
