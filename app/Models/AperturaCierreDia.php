<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Observers\AperturaCierreDiaObserver;
use App\Traits\BelongsToSede;
use App\Models\Excedente;
use App\Models\SolicitudResolucionExcedente;

class AperturaCierreDia extends Model
{
    use BelongsToSede;
    protected $table = 'apertura_cierre_dia';
    protected $primaryKey = 'AperturaCierreDiaID';

    protected $fillable = [
        'Fecha',
        'FechaApertura',
        'FechaCierre',
        'EstadoDia',
        'UsuarioAperturaID',
        'UsuarioCierreID',
        'Observaciones',
        'SedeID',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'FechaApertura' => 'datetime',
        'FechaCierre' => 'datetime',
    ];

    /**
     * Registrar el Observer para los eventos de este modelo
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(AperturaCierreDiaObserver::class);
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioAperturaID', 'id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UsuarioCierreID', 'id');
    }

    /**
     * Obtiene el registro de apertura/cierre del día actual
     */
    public static function hoyOHoy(): ?self
    {
        return self::whereDate('Fecha', today())->first();
    }

    /**
     * Verifica si hay un día abierto para operaciones.
     * Solo permite UN día abierto a la vez. Puede ser hoy o un día pasado.
     * Si hay múltiples abiertos, deja solo el más reciente y cierra los demás.
     */
    public static function estaAbierto(): bool
    {
        $abiertos = self::where('EstadoDia', 'ABIERTO')
            ->orderBy('Fecha', 'desc')
            ->get();

        if ($abiertos->isEmpty()) {
            return false;
        }

        if ($abiertos->count() > 1) {
            $primero = $abiertos->shift();
            foreach ($abiertos as $dia) {
                \Illuminate\Support\Facades\Log::warning('AperturaCierreDia: Cerrando día abierto duplicado', [
                    'AperturaCierreDiaID' => $dia->AperturaCierreDiaID,
                    'Fecha' => $dia->Fecha->toDateString(),
                    'SedeID' => $dia->SedeID,
                    'DiaConservado' => $primero->Fecha->toDateString(),
                ]);
                $dia->update(['EstadoDia' => 'CERRADO', 'FechaCierre' => now()]);
            }
        }

        return self::where('EstadoDia', 'ABIERTO')->exists();
    }

    /**
     * Obtiene el día abierto (el único, o el más reciente si hubo duplicados).
     */
    public static function getDiaAbierto(): ?self
    {
        return self::where('EstadoDia', 'ABIERTO')
            ->orderBy('Fecha', 'desc')
            ->first();
    }

    /**
     * Verifica si el día ACTUAL (hoy) está abierto
     * Diferente a estaAbierto() que verifica si hay ALGÚN día abierto
     */
    public static function estaAbiertoHoy(): bool
    {
        $hoy = self::hoyOHoy();
        return $hoy && $hoy->EstadoDia === 'ABIERTO';
    }

    /**
     * Obtiene el estado del día actual
     */
    public static function estadoDiaActual(): string
    {
        $hoy = self::hoyOHoy();
        return $hoy?->EstadoDia ?? 'CERRADO';
    }

    /**
     * Cierra el día: marca con FechaCierre todos los registros sin cerrar DEL DÍA ESPECÍFICO
     */
    public function cerrarDia(): void
    {
        try {
            $fecha = $this->Fecha->toDateString(); // '2025-01-22'
            $fechaCarbon = $this->Fecha->startOfDay(); // Carbon object as timestamp
            \Illuminate\Support\Facades\Log::info("Iniciando cerrarDia para fecha: {$fecha}");

            // Cerrar clientes SIN CERRAR registrados ese día
            $clientesActualizados = Cliente::whereNull('FechaCierre')
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Clientes cerrados en {$fecha}: {$clientesActualizados}");

            // Cerrar proposiciones SIN CERRAR propuestas ese día (usa FechaPropuesta)
            $proposicionesActualizadas = ProposicionCredito::whereNull('FechaCierre')
                ->whereDate('FechaPropuesta', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Proposiciones cerradas en {$fecha}: {$proposicionesActualizadas}");

            // Cerrar créditos SIN CERRAR generados ese día (usa FechaGeneracion)
            $creditosActualizados = Credito::whereNull('FechaCierre')
                ->whereDate('FechaGeneracion', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Créditos cerrados en {$fecha}: {$creditosActualizados}");

            // Cerrar pagos SIN CERRAR registrados ese día (usa FechaPago)
            $pagosActualizados = Pago::whereNull('FechaCierre')
                ->whereDate('FechaPago', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Pagos cerrados en {$fecha}: {$pagosActualizados}");

            // Cerrar cuotas SIN CERRAR creadas ese día (usa FechaCreacion)
            $cuotasActualizadas = Cuota::whereNull('FechaCierre')
                ->whereDate('FechaCreacion', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Cuotas cerradas en {$fecha}: {$cuotasActualizadas}");

            // Cerrar análisis económicos SIN CERRAR creados ese día (usa FechaAnalisis)
            $analisisActualizados = AnalisisEconomico::whereNull('FechaCierre')
                ->whereDate('FechaAnalisis', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Análisis económicos cerrados en {$fecha}: {$analisisActualizados}");

            $evaluacionesActualizadas = EvaluacionCredito::whereNull('FechaCierre')
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Evaluaciones de crédito cerradas en {$fecha}: {$evaluacionesActualizadas}");

            // Cerrar excedentes SIN CERRAR registrados ese día (usa Fecha)
            $excedentesActualizados = Excedente::whereNull('FechaCierre')
                ->whereDate('Fecha', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Excedentes cerrados en {$fecha}: {$excedentesActualizados}");

            // Cerrar solicitudes resolucion SIN CERRAR creadas ese día (usa created_at)
            $solicitudesActualizadas = SolicitudResolucionExcedente::whereNull('FechaCierre')
                ->whereDate('created_at', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Solicitudes de resolución de excedentes cerradas en {$fecha}: {$solicitudesActualizadas}");

            \Illuminate\Support\Facades\Log::info("Día cerrado exitosamente: {$fecha}");

            $this->registrarHistorial('CERRAR', $clientesActualizados + $proposicionesActualizadas + $creditosActualizados + $pagosActualizados + $cuotasActualizadas + $analisisActualizados + $evaluacionesActualizadas + $excedentesActualizados + $solicitudesActualizadas);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generando cierre de día: ' . $e->getMessage(), [
                'exception' => $e,
                'fecha' => $this->Fecha
            ]);
            throw $e;
        }
    }

    /**
     * Reabre el día: elimina FechaCierre de todos los registros del día ESPECÍFICO
     */
    public function reabrirDia(): void
    {
        $logFile = storage_path('logs/reopening-debug.log');
        file_put_contents($logFile, "\n\n========== INICIANDO REABRIRDIA ==========\n", FILE_APPEND);

        try {
            $fecha = $this->Fecha->toDateString(); // '2026-01-20'
            file_put_contents($logFile, "Fecha a reabrir: {$fecha}\n", FILE_APPEND);

            $fechaInicio = $this->Fecha->copy()->startOfDay();
            $fechaFin = $this->Fecha->copy()->endOfDay();

            file_put_contents($logFile, "Fecha inicio: " . $fechaInicio->toDateTimeString() . "\n", FILE_APPEND);
            file_put_contents($logFile, "Fecha fin: " . $fechaFin->toDateTimeString() . "\n", FILE_APPEND);

            // Reabrir clientes registrados ese día
            $clientesActualizados = Cliente::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Clientes actualizados: {$clientesActualizados}\n", FILE_APPEND);

            // Reabrir proposiciones propuestas ese día (usa FechaPropuesta)
            $proposicionesActualizadas = ProposicionCredito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaPropuesta', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Proposiciones actualizadas: {$proposicionesActualizadas}\n", FILE_APPEND);

            // Reabrir créditos generados ese día (usa FechaGeneracion)
            $creditosActualizados = Credito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaGeneracion', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Créditos actualizados: {$creditosActualizados}\n", FILE_APPEND);

            // Reabrir pagos creados ese día (buscar por FechaPago)
            $pagosAntes = Pago::whereBetween('FechaPago', [$fechaInicio, $fechaFin])->count();
            file_put_contents($logFile, "\nPagos encontrados antes de actualizar: {$pagosAntes}\n", FILE_APPEND);

            $pagosActualizados = Pago::whereBetween('FechaPago', [$fechaInicio, $fechaFin])
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Pagos actualizados: {$pagosActualizados}\n", FILE_APPEND);

            $pagosDespues = Pago::whereBetween('FechaPago', [$fechaInicio, $fechaFin])
                ->whereNull('FechaCierre')
                ->count();
            file_put_contents($logFile, "Pagos con FechaCierre NULL después: {$pagosDespues}\n", FILE_APPEND);

            // Reabrir cuotas creadas ese día (usa FechaCreacion)
            $cuotasActualizadas = Cuota::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaCreacion', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Cuotas actualizadas: {$cuotasActualizadas}\n", FILE_APPEND);

            // Reabrir análisis económicos creados ese día (usa FechaAnalisis)
            $analisisActualizados = AnalisisEconomico::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaAnalisis', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Análisis económicos actualizados: {$analisisActualizados}\n", FILE_APPEND);

            $evaluacionesActualizadas = EvaluacionCredito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Evaluaciones actualizadas: {$evaluacionesActualizadas}\n", FILE_APPEND);

            // Reabrir excedentes registrados ese día (usa Fecha)
            $excedentesActualizados = Excedente::whereDate('FechaCierre', $fecha)
                ->whereDate('Fecha', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Excedentes actualizados: {$excedentesActualizados}\n", FILE_APPEND);

            // Reabrir solicitudes resolucion creadas ese día (usa created_at)
            $solicitudesActualizadas = SolicitudResolucionExcedente::whereDate('FechaCierre', $fecha)
                ->whereDate('created_at', $fecha)
                ->update(['FechaCierre' => null]);
            file_put_contents($logFile, "Solicitudes de resolución actualizadas: {$solicitudesActualizadas}\n", FILE_APPEND);

            file_put_contents($logFile, "\n========== REABRIRDIA COMPLETADO ==========\n", FILE_APPEND);

            \Illuminate\Support\Facades\Log::info("Día reabierto: {$fecha}", [
                'usuario' => auth()->user()?->name,
                'timestamp' => now()
            ]);

            $this->registrarHistorial('REABRIR', $clientesActualizados + $proposicionesActualizadas + $creditosActualizados + $pagosActualizados + $cuotasActualizadas + $analisisActualizados + $evaluacionesActualizadas + $excedentesActualizados + $solicitudesActualizadas);

        } catch (\Exception $e) {
            $logFile = storage_path('logs/reopening-debug.log');
            file_put_contents($logFile, "ERROR en reabrirDia: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "Stack trace: " . $e->getTraceAsString() . "\n\n", FILE_APPEND);

            \Illuminate\Support\Facades\Log::error('Error reabriendo día: ' . $e->getMessage(), [
                'fecha' => $this->Fecha,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * Registrar en bitácora de auditoría el evento de cierre o reapertura.
     */
    private function registrarHistorial(string $accion, int $cantidadRegistros): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('historial_apertura_cierre')->insert([
                'SedeID' => $this->SedeID,
                'Fecha' => $this->Fecha->toDateString(),
                'Accion' => $accion,
                'UsuarioID' => auth()->id(),
                'Observaciones' => $this->Observaciones,
                'CantidadRegistrosAfectados' => $cantidadRegistros,
                'FechaHora' => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error registrando historial de {$accion}", [
                'fecha' => $this->Fecha,
                'error' => $e->getMessage()
            ]);
        }
    }
}
