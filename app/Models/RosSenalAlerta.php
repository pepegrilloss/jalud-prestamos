<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use App\Traits\ResolvesRosCasoSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosSenalAlerta extends Model
{
    use BelongsToSede, ResolvesRosCasoSede;

    protected $table = 'ros_senales_alerta';
    protected $primaryKey = 'RosSenalAlertaID';
    protected $fillable = ['RosCasoID', 'SedeID', 'Tipo', 'Codigo', 'Descripcion'];

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
}
