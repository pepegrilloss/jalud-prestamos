<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use App\Traits\ResolvesRosCasoSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosAdjunto extends Model
{
    use BelongsToSede, ResolvesRosCasoSede;

    protected $table = 'ros_adjuntos';
    protected $primaryKey = 'RosAdjuntoID';
    protected $fillable = [
        'RosCasoID', 'SedeID', 'RutaArchivo', 'NombreOriginal', 'TipoMime', 'TamanioBytes',
        'Descripcion', 'SubidoPorID',
    ];
    protected $casts = ['TamanioBytes' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $adjunto): void {
            $adjunto->SubidoPorID ??= auth()->id();
        });
    }

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
    public function subidoPor(): BelongsTo { return $this->belongsTo(User::class, 'SubidoPorID'); }
}
