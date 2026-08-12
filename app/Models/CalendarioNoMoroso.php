<?php

namespace App\Models;

use App\Services\CalendarioLaboralService;
use App\Services\CreditoFechaRecalculationService;
use App\Traits\BelongsToSede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CalendarioNoMoroso extends Model
{
    use BelongsToSede;

    public const TIPO_NO_LABORABLE = 'NO_LABORABLE';

    public const TIPO_LABORABLE_FORZADO = 'LABORABLE_FORZADO';

    protected $primaryKey = 'CalendarioNoMorosoID';

    protected $table = 'calendario_no_morosos';

    public $timestamps = false;

    protected $fillable = [
        'Fecha',
        'Descripcion',
        'Tipo',
        'Activo',
        'SedeID',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'Activo' => 'boolean',
        'FechaCreacion' => 'datetime',
        'FechaModificacion' => 'datetime',
    ];

    public static function tipos(): array
    {
        return [
            self::TIPO_NO_LABORABLE => 'No laborable',
            self::TIPO_LABORABLE_FORZADO => 'Laborable forzado',
        ];
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'SedeID', 'SedeID');
    }

    protected static function booted(): void
    {
        static::creating(function (CalendarioNoMoroso $calendario) {
            $calendario->Tipo = $calendario->Tipo ?: self::TIPO_NO_LABORABLE;
        });

        static::created(function (CalendarioNoMoroso $calendario) {
            CalendarioLaboralService::clearCache();
            self::recalcularCreditosAfectados(
                [(int) $calendario->SedeID],
                "Creacion de regla {$calendario->Tipo} para {$calendario->Fecha?->toDateString()}"
            );
        });

        static::updated(function (CalendarioNoMoroso $calendario) {
            CalendarioLaboralService::clearCache();

            if (! $calendario->wasChanged(['Fecha', 'Tipo', 'Activo', 'SedeID'])) {
                return;
            }

            self::recalcularCreditosAfectados(
                array_unique([
                    (int) $calendario->getOriginal('SedeID'),
                    (int) $calendario->SedeID,
                ]),
                "Edicion de regla de calendario #{$calendario->CalendarioNoMorosoID}"
            );
        });

        static::deleted(function (CalendarioNoMoroso $calendario) {
            CalendarioLaboralService::clearCache();
            self::recalcularCreditosAfectados(
                [(int) $calendario->SedeID],
                "Eliminacion de regla de calendario #{$calendario->CalendarioNoMorosoID}"
            );
        });
    }

    private static function recalcularCreditosAfectados(array $sedes, string $motivo): void
    {
        foreach (array_filter($sedes) as $sedeId) {
            try {
                $resultado = CreditoFechaRecalculationService::recalcularSede((int) $sedeId, $motivo);
                if ($resultado['errores'] > 0) {
                    Log::warning('El cambio de calendario termino con creditos no recalculados.', [
                        'SedeID' => $sedeId,
                        'motivo' => $motivo,
                        'resultado' => $resultado,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('No se pudieron recalcular los creditos despues de cambiar el calendario.', [
                    'SedeID' => $sedeId,
                    'motivo' => $motivo,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }
    }
}
