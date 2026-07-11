<?php

namespace App\Services;

use Carbon\Carbon;

class DiasHabilesCalculator
{
    public static function contarDiasHabiles(Carbon $desde, Carbon $hasta, ?int $sedeId = null): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if ($desde->gt($hasta)) {
            return 0;
        }

        $dias = 0;
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            if (CalendarioLaboralService::esLaborable($fecha, $sedeId)) {
                $dias++;
            }

            $fecha->addDay();
        }

        return $dias;
    }
}
