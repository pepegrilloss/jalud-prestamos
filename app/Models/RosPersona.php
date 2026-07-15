<?php

namespace App\Models;

use App\Traits\BelongsToSede;
use App\Traits\ResolvesRosCasoSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosPersona extends Model
{
    use BelongsToSede, ResolvesRosCasoSede;

    protected $table = 'ros_personas';
    protected $primaryKey = 'RosPersonaID';
    protected $fillable = [
        'RosCasoID', 'SedeID', 'ClienteID', 'TipoPersona', 'CondicionParticipacion',
        'ApellidoPaterno', 'ApellidoMaterno', 'Nombres', 'RazonSocial', 'TipoDocumento',
        'NumeroDocumento', 'FechaNacimiento', 'Nacionalidad', 'EsPep', 'ProfesionOcupacion',
        'ActividadEconomica', 'Domicilio', 'Telefono', 'Correo', 'IngresoMensual', 'MonedaIngreso',
    ];
    protected $casts = ['FechaNacimiento' => 'date', 'EsPep' => 'boolean', 'IngresoMensual' => 'decimal:2'];

    public function caso(): BelongsTo { return $this->belongsTo(RosCaso::class, 'RosCasoID', 'RosCasoID'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class, 'ClienteID', 'ClienteID'); }
}
