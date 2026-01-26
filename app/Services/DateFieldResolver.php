<?php

namespace App\Services;

use App\Models\AperturaCierreDia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio que resuelve automáticamente cuál es el campo de fecha
 * que debe ser inyectado con la fecha del día abierto
 */
class DateFieldResolver
{
    /**
     * Mapeo de modelos con sus campos de fecha para inserción automática
     * @var array<string, string|null>
     */
    private static array $dateFieldMap = [
        'Cliente' => 'FechaRegistro',
        'ProposicionCredito' => 'FechaPropuesta',
        'Credito' => 'FechaGeneracion',
        'Pago' => 'FechaPago',
        'Cuota' => 'FechaCreacion',
        'Giro' => 'FechaCreacion',
        'TipoCredito' => 'FechaCreacion',
        'TipoPago' => 'FechaCreacion',
        'Zona' => 'FechaCreacion',
        'PromotorCobrador' => 'FechaCreacion',
        'SubGiro' => 'FechaCreacion',
        'NivelAprobacion' => 'FechaCreacion',
        'AprobacionProposicion' => 'FechaAprobacion',
        'AnalisisEconomico' => 'FechaAnalisis',
        'Log' => 'created_at',
    ];

    /**
     * Obtiene el campo de fecha correspondiente para un modelo
     * 
     * @param Model|string $model
     * @return string|null El nombre del campo de fecha, o null si no está mapeado
     */
    public static function getDateField(Model|string $model): ?string
    {
        $modelClass = is_string($model) ? $model : class_basename($model);
        return self::$dateFieldMap[$modelClass] ?? null;
    }

    /**
     * Obtiene la fecha del día actualmente abierto
     * 
     * @return Carbon|null La fecha abierta, o null si no hay día abierto
     */
    public static function getFechaAbierta(): ?Carbon
    {
        $diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();
        return $diaAbierto?->Fecha ?? null;
    }

    /**
     * Inyecta la fecha abierta en los datos antes de crear un registro
     * 
     * @param array $data Los datos del formulario
     * @param Model|string $model El modelo a crear
     * @return array Los datos con la fecha inyectada
     */
    public static function injectFechaAbierta(array $data, Model|string $model): array
    {
        $dateField = self::getDateField($model);
        
        if ($dateField && !isset($data[$dateField])) {
            $fechaAbierta = self::getFechaAbierta();
            
            if ($fechaAbierta) {
                // Si el campo es 'created_at', usar el timestamp actual
                if ($dateField === 'created_at') {
                    $data[$dateField] = now();
                } else {
                    // Para otros campos de fecha, usar solo la fecha del día abierto
                    $data[$dateField] = $fechaAbierta;
                }
            }
        }
        
        return $data;
    }

    /**
     * Verifica si un modelo debe tener inyección automática de fecha
     * 
     * @param Model|string $model
     * @return bool
     */
    public static function shouldInjectDate(Model|string $model): bool
    {
        return self::getDateField($model) !== null;
    }

    /**
     * Obtiene todos los modelos que soportan inyección automática
     * 
     * @return array<string>
     */
    public static function getSupportedModels(): array
    {
        return array_keys(self::$dateFieldMap);
    }

    /**
     * Registra un nuevo mapeo de modelo
     * 
     * @param string $modelClass
     * @param string $dateField
     */
    public static function register(string $modelClass, string $dateField): void
    {
        self::$dateFieldMap[$modelClass] = $dateField;
    }
}
