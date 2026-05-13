<?php

namespace App\Services;

use App\Models\CalendarioNoMoroso;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DiasHabilesCalculator
{
    private static ?array $feriadosNager = null;
    private static ?array $fechasNoMorosas = null;

    /**
     * Cargar y cachear feriados nacionales de Perú (API Nager) para los años relevantes.
     */
    private static function cargarFeriados(): void
    {
        if (self::$feriadosNager !== null) return;

        self::$feriadosNager = [];
        $annoActual = now()->year;

        for ($anno = $annoActual - 2; $anno <= $annoActual + 1; $anno++) {
            try {
                $response = Http::timeout(5)->retry(2, 100)
                    ->get("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                $feriados = $response->json();
                if ($feriados) {
                    foreach ($feriados as $feriado) {
                        self::$feriadosNager[$feriado['date']] = $feriado['localName'];
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Cargar y cachear fechas del calendario no moroso.
     */
    private static function cargarNoMorosos(): void
    {
        if (self::$fechasNoMorosas !== null) return;

        self::$fechasNoMorosas = CalendarioNoMoroso::where('Activo', true)
            ->get()
            ->map(fn($item) => Carbon::parse($item->Fecha)->toDateString())
            ->toArray();
    }

    /**
     * Contar días hábiles (lunes a sábado, no feriados nacionales ni locales) entre dos fechas.
     */
    public static function contarDiasHabiles(Carbon $desde, Carbon $hasta): int
    {
        self::cargarFeriados();
        self::cargarNoMorosos();

        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if ($desde->gt($hasta)) return 0;

        $dias = 0;
        $fecha = $desde->copy();

        while ($fecha->lte($hasta)) {
            $fechaStr = $fecha->toDateString();
            $esDomingo = $fecha->dayOfWeek === 0;
            $esFeriado = isset(self::$feriadosNager[$fechaStr]);
            $esNoMoroso = in_array($fechaStr, self::$fechasNoMorosas);

            if (!$esDomingo && !$esFeriado && !$esNoMoroso) {
                $dias++;
            }

            $fecha->addDay();
        }

        return $dias;
    }
}
