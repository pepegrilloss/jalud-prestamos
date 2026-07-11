<?php

namespace App\Services;

use App\Models\CalendarioNoMoroso;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class CalendarioLaboralService
{
    private static array $feriadosNagerPorAnio = [];
    private static ?array $reglasLocales = null;

    public static function clearCache(): void
    {
        self::$reglasLocales = null;
    }

    public static function esLaborable(Carbon|string $fecha, ?int $sedeId = null): bool
    {
        $fecha = self::normalizarFecha($fecha);
        $reglaLocal = self::obtenerReglaLocal($fecha, $sedeId);

        if (($reglaLocal['tipo'] ?? null) === CalendarioNoMoroso::TIPO_LABORABLE_FORZADO) {
            return true;
        }

        if (($reglaLocal['tipo'] ?? null) === CalendarioNoMoroso::TIPO_NO_LABORABLE) {
            return false;
        }

        if ($fecha->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }

        return !self::esFeriadoNacional($fecha);
    }

    public static function esNoLaborable(Carbon|string $fecha, ?int $sedeId = null): bool
    {
        return !self::esLaborable($fecha, $sedeId);
    }

    public static function motivoNoLaborable(Carbon|string $fecha, ?int $sedeId = null): ?string
    {
        $fecha = self::normalizarFecha($fecha);
        $reglaLocal = self::obtenerReglaLocal($fecha, $sedeId);

        if (($reglaLocal['tipo'] ?? null) === CalendarioNoMoroso::TIPO_LABORABLE_FORZADO) {
            return null;
        }

        if (($reglaLocal['tipo'] ?? null) === CalendarioNoMoroso::TIPO_NO_LABORABLE) {
            return $reglaLocal['descripcion'] ?: 'CALENDARIO NO MOROSO';
        }

        if ($fecha->dayOfWeek === Carbon::SUNDAY) {
            return 'DOMINGO';
        }

        $feriado = self::obtenerFeriadoNacional($fecha);

        return $feriado ? "FERIADO ({$feriado})" : null;
    }

    public static function siguienteDiaLaborable(Carbon|string $fecha, ?int $sedeId = null): Carbon
    {
        $fecha = self::normalizarFecha($fecha);

        while (self::esNoLaborable($fecha, $sedeId)) {
            $fecha->addDay();
        }

        return $fecha;
    }

    public static function esFeriadoNacional(Carbon|string $fecha): bool
    {
        return self::obtenerFeriadoNacional($fecha) !== null;
    }

    public static function obtenerFeriadoNacional(Carbon|string $fecha): ?string
    {
        $fecha = self::normalizarFecha($fecha);
        $feriados = self::feriadosNager($fecha->year);

        return $feriados[$fecha->toDateString()] ?? null;
    }

    private static function obtenerReglaLocal(Carbon $fecha, ?int $sedeId = null): ?array
    {
        self::cargarReglasLocales();

        $sedeId = $sedeId ?? self::resolverSedeId();
        if (!$sedeId) {
            return null;
        }

        return self::$reglasLocales[$sedeId][$fecha->toDateString()] ?? null;
    }

    private static function cargarReglasLocales(): void
    {
        if (self::$reglasLocales !== null) {
            return;
        }

        self::$reglasLocales = [];

        CalendarioNoMoroso::withoutGlobalScope('sede')
            ->where('Activo', true)
            ->get(['Fecha', 'Tipo', 'Descripcion', 'SedeID'])
            ->each(function (CalendarioNoMoroso $item) {
                if (!$item->SedeID) {
                    return;
                }

                self::$reglasLocales[$item->SedeID][Carbon::parse($item->Fecha)->toDateString()] = [
                    'tipo' => $item->Tipo ?: CalendarioNoMoroso::TIPO_NO_LABORABLE,
                    'descripcion' => $item->Descripcion,
                ];
            });
    }

    private static function feriadosNager(int $anio): array
    {
        if (array_key_exists($anio, self::$feriadosNagerPorAnio)) {
            return self::$feriadosNagerPorAnio[$anio];
        }

        self::$feriadosNagerPorAnio[$anio] = [];

        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get("https://date.nager.at/api/v3/PublicHolidays/{$anio}/PE");

            foreach (($response->json() ?: []) as $feriado) {
                if (isset($feriado['date'])) {
                    self::$feriadosNagerPorAnio[$anio][$feriado['date']] = $feriado['localName'] ?? 'Feriado nacional';
                }
            }
        } catch (\Exception $e) {
        }

        return self::$feriadosNagerPorAnio[$anio];
    }

    private static function normalizarFecha(Carbon|string $fecha): Carbon
    {
        return $fecha instanceof Carbon
            ? $fecha->copy()->startOfDay()
            : Carbon::parse($fecha)->startOfDay();
    }

    private static function resolverSedeId(): ?int
    {
        try {
            return session('sede_activa') ?? auth()->user()?->getEffectiveSedeId();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
