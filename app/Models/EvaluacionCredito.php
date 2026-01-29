<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionCredito extends Model
{
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