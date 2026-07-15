<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use App\Traits\ResolvesRosCasoSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosTipologia extends Model
{
    use BelongsToSede, ResolvesRosCasoSede;

    protected $table = 'ros_tipologias';
    protected $primaryKey = 'RosTipologiaID';
    protected $fillable = ['RosCasoID', 'SedeID', 'Codigo', 'Descripcion'];

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
}
