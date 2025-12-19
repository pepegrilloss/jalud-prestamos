<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNivelAprobacion extends Model
{
    protected $table = 'UserNivelAprobacion';
    protected $primaryKey = 'UserNivelAprobacionID';
    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'NivelAprobacionID',
        'FechaAsignacion',
        'Activo',
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
