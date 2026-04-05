<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\BelongsToSede;

class RegistrarEvaluacionDeCredito extends Model
{
    use BelongsToSede;

    protected $table = 'Cliente';
    protected $primaryKey = 'ClienteID';
    public $timestamps = false;

    protected $fillable = [
        'DNI',
        'NombresApellidos',
        'MontoMaxRecomendado',
        'SedeID',
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

    public function analisisEconomico(): HasOne
    {
        return $this->hasOne(AnalisisEconomico::class, 'ClienteID', 'ClienteID')
            ->where('Activo', 1)
            ->latest('FechaAnalisis');
    }

    public function negocio(): HasOne
    {
        return $this->hasOne(Negocio::class, 'ClienteID', 'ClienteID');
    }
}