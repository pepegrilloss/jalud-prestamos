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

    protected static function booted()
    {
        static::created(function ($calendario) {
            // El campo Activo está oculto en la creación de Filament, por lo que podría venir como null en la instancia.
            // Asumimos true a menos que explícitamente sea false.
            if ($calendario->Activo !== false) {
                
                // --- 1. PRECARGAR FERIADOS NACIONALES (API) ---
                $feriadosData = [];
                try {
                    $annoActual = now()->year;
                    // Cargamos este año y el siguiente por si acaso
                    for ($anno = $annoActual; $anno <= $annoActual + 1; $anno++) {
                        try {
                            $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                            $feriados = json_decode($response, true);
                            foreach ($feriados as $feriado) {
                                $feriadosData[$feriado['date']] = true;
                            }
                        } catch (\Exception $e) { }
                    }
                } catch (\Exception $e) { }

                // --- 2. PRECARGAR FECHAS NO MOROSAS (LOCALES) ---
                $fechasNoMorosas = \App\Models\CalendarioNoMoroso::where('Activo', true)
                    ->get()
                    ->map(fn($item) => \Carbon\Carbon::parse($item->Fecha)->toDateString())
                    ->toArray();

                // --- 3. OBTENER CRÉDITOS ACTIVOS ---
                $creditos = \App\Models\Credito::where('Activo', 1)->with('proposicion')->get();

                foreach ($creditos as $credito) {
                    // Solo actualizar si la fecha no morosa cae DENTRO de la vida del crédito (entre su inicio y su vencimiento actual)
                    $fechaNoMorosa = \Carbon\Carbon::parse($calendario->Fecha)->startOfDay();
                    
                    // Si FechaInicio es nulo en la BD, usamos FechaGeneracion
                    $inicioStr = $credito->FechaInicio ?: $credito->FechaGeneracion;
                    $fechaInicio = $inicioStr ? \Carbon\Carbon::parse($inicioStr)->startOfDay() : null;
                    
                    $fechaVenc = $credito->FechaVencimiento ? \Carbon\Carbon::parse($credito->FechaVencimiento)->startOfDay() : null;

                    if ($fechaInicio && $fechaVenc) {
                        // Validamos: la fecha del feriado debe ser >= inicio y <= vencimiento
                        if ($fechaNoMorosa->greaterThanOrEqualTo($fechaInicio) && $fechaNoMorosa->lessThanOrEqualTo($fechaVenc)) {
                            // OPTIMIZADO: Leer SaldoPendiente de la columna
                            $saldo = (float) ($credito->proposicion?->SaldoPendiente ?? 0);
                            
                            if ($saldo > 0) {
                                \Log::info("Actualizando crédito " . $credito->CreditoID . " por nueva fecha no morosa. Saldo: " . $saldo);
                                
                                // Sumarle 1 día inicial
                                $fechaVenc->addDay();
                                
                                // Seguir sumando si cae en domingo, feriado nacional, o feriado local
                                while (
                                    $fechaVenc->dayOfWeek == 0 || // Domingo
                                    isset($feriadosData[$fechaVenc->toDateString()]) || // Feriado Nacional API
                                    in_array($fechaVenc->toDateString(), $fechasNoMorosas) // Feriado Local (CalendarioNoMoroso)
                                ) {
                                    $fechaVenc->addDay();
                                }

                                $credito->FechaVencimiento = $fechaVenc;
                                $credito->save();
                            }
                        }
                    }
                }
            }
        });
    }
}
