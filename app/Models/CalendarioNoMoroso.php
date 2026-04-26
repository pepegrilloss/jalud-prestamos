<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class CalendarioNoMoroso extends Model
{
    use BelongsToSede;

    protected $primaryKey = 'CalendarioNoMorosoID';
    protected $table = 'calendario_no_morosos';
    public $timestamps = false;

    protected $fillable = [
        'Fecha',
        'Descripcion',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }
}
