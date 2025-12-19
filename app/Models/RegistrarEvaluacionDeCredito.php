<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrarEvaluacionDeCredito extends Model
{
    protected $table = 'Cliente';
    protected $primaryKey = 'ClienteID';
    public $timestamps = false;

    protected $fillable = [
        'DNI',
        'NombresApellidos',
        'MontoMaxRecomendado',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaRegistro' => 'datetime',
        'FechaModificacion' => 'datetime',
        'Activo' => 'boolean',
        'MontoMaxRecomendado' => 'decimal:2',
    ];

    // AGREGAR ESTA RELACIÓN
    public function evaluacionesCredito(): HasMany
    {
        return $this->hasMany(EvaluacionCredito::class, 'ClienteID', 'ClienteID');
    }
}