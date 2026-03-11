<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSede;

class UserNivelAprobacion extends Model
{
    use BelongsToSede;
    protected $table = 'UserNivelAprobacion';
    protected $primaryKey = 'UserNivelAprobacionID';
    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'NivelAprobacionID',
        'FechaAsignacion',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'FechaAsignacion' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID');
    }

    public function nivelAprobacion()
    {
        return $this->belongsTo(NivelAprobacion::class, 'NivelAprobacionID');
    }
}
