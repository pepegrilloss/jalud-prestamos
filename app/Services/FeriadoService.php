<?php

namespace App\Services;

use App\Models\FeriadoPeru;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio central de feriados nacionales de Peru.
 *
 * Fuente externa: Calendarific (country=PE, type=national).
 * Los feriados se almacenan localmente en la tabla `feriados_peru`.
 * La API externa se consulta como MAXIMO 1 vez cada 24 horas por anio.
 * Toda lectura normal del sistema debe pasar por obtenerFeriados().
 */
class FeriadoService
{
    private const CACHE_TTL_HORAS = 24;

    /** Cache estatica por request (evita consultas repetidas a BD dentro del mismo request). */
    private static array $feriadosPorAnio = [];

    /**
     * Traduccion al espanol de los nombres que Calendarific devuelve en ingles.
     * Solo cubre los feriados nacionales de Peru (type=national).
     * Si un nombre no esta en el mapa, se conserva el original.
     */
    private const NOMBRES_ES = [
        'New Year\'s Day' => 'Año Nuevo',
        'Public Sector Holiday' => 'Día no laborable (Sector Público)',
        'Maundy Thursday' => 'Jueves Santo',
        'Good Friday' => 'Viernes Santo',
        'Labor Day' => 'Día del Trabajo',
        'Battle of Arica' => 'Batalla de Arica y Día de la Bandera',
        'St Peter and St Paul' => 'San Pedro y San Pablo',
        'Peruvian Air Force Day' => 'Día de la Fuerza Aérea del Perú',
        'Independence Day' => 'Día de la Independencia',
        'Independence Day (day 2)' => 'Día de la Independencia (2° día)',
        'Battle of Junín' => 'Batalla de Junín',
        'Santa Rosa De Lima' => 'Santa Rosa de Lima',
        'Battle of Angamos' => 'Batalla de Angamos',
        'All Saints\' Day' => 'Todos los Santos',
        'Feast of the Immaculate Conception' => 'Inmaculada Concepción',
        'Battle of Ayacucho' => 'Batalla de Ayacucho',
        'Christmas Day' => 'Navidad',
        'Restoration Day' => 'Día de la Restauración',
        'Easter Sunday' => 'Domingo de Resurrección',
        'Fiestas Patrias' => 'Fiestas Patrias',
    ];

    private static function traducirNombre(string $nombre): string
    {
        return self::NOMBRES_ES[$nombre] ?? $nombre;
    }

    /**
     * Devuelve los feriados nacionales de un anio como ['YYYY-MM-DD' => 'Nombre'].
     * Si no hay datos locales, intenta sincronizar (max 1 vez / 24h / anio).
     * Si la sincronizacion falla, devuelve lo almacenado (o [] si no hay nada).
     * NUNCA lanza excepciones: el sistema debe seguir funcionando si la API falla.
     */
    public static function obtenerFeriados(int $anio): array
    {
        if (isset(self::$feriadosPorAnio[$anio])) {
            return self::$feriadosPorAnio[$anio];
        }

        $feriados = self::leerDeBD($anio);

        if (empty($feriados)) {
            self::sincronizarAnio($anio);
            $feriados = self::leerDeBD($anio);
        }

        return self::$feriadosPorAnio[$anio] = $feriados;
    }

    /**
     * Lee los feriados del anio desde la BD local.
     */
    public static function leerDeBD(int $anio): array
    {
        return FeriadoPeru::where('anio', $anio)
            ->orderBy('fecha')
            ->get()
            ->mapWithKeys(fn (FeriadoPeru $f) => [
                $f->fecha->format('Y-m-d') => $f->nombre,
            ])
            ->toArray();
    }

    /**
     * Sincroniza los feriados nacionales de Peru de un anio desde Calendarific.
     * Respeta el limite de 1 sincronizacion cada 24 horas por anio.
     *
     * @return int cantidad de feriados sincronizados (0 si ya se sincronizo hoy o si fallo)
     */
    public static function sincronizarAnio(int $anio): int
    {
        $cacheKey = "feriados_sync_{$anio}";

        if (Cache::has($cacheKey)) {
            return 0;
        }

        $apiKey = config('services.calendarific.api_key');
        if (empty($apiKey)) {
            Log::error('[Feriados] Calendarific: API key no configurada (CALENDARIFIC_API_KEY).', ['anio' => $anio]);

            return 0;
        }

        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get('https://calendarific.com/api/v2/holidays', [
                    'api_key' => $apiKey,
                    'country' => config('services.calendarific.country', 'PE'),
                    'year' => $anio,
                    'type' => config('services.calendarific.type', 'national'),
                ]);

            if (! $response->successful()) {
                Log::error('[Feriados] Calendarific: respuesta HTTP '.$response->status().'.', ['anio' => $anio]);

                return 0;
            }

            $data = $response->json();
            $holidays = $data['response']['holidays'] ?? null;

            if (! is_array($holidays)) {
                Log::error('[Feriados] Calendarific: JSON invalido o sin response.holidays.', ['anio' => $anio]);

                return 0;
            }

            $sincronizados = 0;
            foreach ($holidays as $feriado) {
                $iso = $feriado['date']['iso'] ?? null;
                $nombre = $feriado['name'] ?? null;

                if (! $iso || ! $nombre) {
                    continue;
                }

                $fecha = Carbon::parse($iso)->toDateString();

                FeriadoPeru::updateOrCreate(
                    ['fecha' => $fecha],
                    ['nombre' => self::traducirNombre($nombre), 'anio' => $anio]
                );

                $sincronizados++;
            }

            if ($sincronizados > 0) {
                self::$feriadosPorAnio[$anio] = self::leerDeBD($anio);
                CalendarioLaboralService::clearCache();
                Cache::put($cacheKey, now()->toDateTimeString(), now()->addHours(self::CACHE_TTL_HORAS));
            }

            Log::info("[Feriados] Calendarific: {$sincronizados} feriados sincronizados para {$anio}.");

            return $sincronizados;
        } catch (\Throwable $e) {
            Log::error('[Feriados] Calendarific: error de sincronizacion.', [
                'anio' => $anio,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Limpia la cache estatica del request (util para tests).
     */
    public static function clearCache(): void
    {
        self::$feriadosPorAnio = [];
    }
}
