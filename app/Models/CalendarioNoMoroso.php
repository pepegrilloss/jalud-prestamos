<?php

namespace App\Models;

use App\Services\CalendarioLaboralService;
use App\Traits\BelongsToSede;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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

            if ($calendario->Activo === false || $calendario->Tipo !== self::TIPO_NO_LABORABLE) {
                return;
            }

            self::extenderVencimientosPorNuevaFechaNoLaborable($calendario);
        });

        static::updated(fn() => CalendarioLaboralService::clearCache());
        static::deleted(fn() => CalendarioLaboralService::clearCache());
    }

    private static function extenderVencimientosPorNuevaFechaNoLaborable(CalendarioNoMoroso $calendario): void
    {
        $fechaNoLaborable = Carbon::parse($calendario->Fecha)->startOfDay();

        $creditos = Credito::where('Activo', 1)
            ->with('proposicion')
            ->get();

        foreach ($creditos as $credito) {
            $inicioStr = $credito->FechaInicio ?: $credito->FechaGeneracion;
            $fechaInicio = $inicioStr ? Carbon::parse($inicioStr)->startOfDay() : null;
            $fechaVenc = $credito->FechaVencimiento ? Carbon::parse($credito->FechaVencimiento)->startOfDay() : null;

            if (!$fechaInicio || !$fechaVenc) {
                continue;
            }

            if (!$fechaNoLaborable->betweenIncluded($fechaInicio, $fechaVenc)) {
                continue;
            }

            $saldo = (float) ($credito->proposicion?->SaldoPendiente ?? 0);
            if ($saldo <= 0) {
                continue;
            }

            $fechaVenc->addDay();
            $credito->FechaVencimiento = CalendarioLaboralService::siguienteDiaLaborable($fechaVenc, $credito->SedeID);
            $credito->save();
        }
    }
}
