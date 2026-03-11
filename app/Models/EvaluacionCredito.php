<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSede;

class EvaluacionCredito extends Model
{
    use BelongsToSede;
    protected $table = 'EvaluacionCredito';
    protected $primaryKey = 'EvaluacionCreditoID';
    public $timestamps = false;

    protected $fillable = [
        'ClienteID',
        'Comentario',
        'FechaRegistro',
        'UsuarioRegistro',
        'FechaCierre',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'FechaRegistro' => 'datetime',
        'FechaCierre' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(RegistrarEvaluacionDeCredito::class, 'ClienteID', 'ClienteID');
    }
}