<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AperturaCierreDia extends Model
{
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
    ];

    protected $casts = [
        'Fecha' => 'date',
        'FechaApertura' => 'datetime',
        'FechaCierre' => 'datetime',
    ];

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
     * Verifica si ALGÚN día está abierto (sin importar la fecha)
     * Este es el método que usa Filament para validar operaciones
     */
    public static function estaAbierto(): bool
    {
        return self::where('EstadoDia', 'ABIERTO')->exists();
    }

    /**
     * Obtiene el día abierto (cualquiera que sea)
     */
    public static function getDiaAbierto(): ?self
    {
        return self::where('EstadoDia', 'ABIERTO')->first();
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

            // Cerrar evaluaciones SIN CERRAR creadas ese día (usa FechaRegistro)
            $evaluacionesActualizadas = EvaluacionCredito::whereNull('FechaCierre')
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => $fechaCarbon]);
            \Illuminate\Support\Facades\Log::info("Evaluaciones de crédito cerradas en {$fecha}: {$evaluacionesActualizadas}");

            \Illuminate\Support\Facades\Log::info("Día cerrado exitosamente: {$fecha}");

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
        try {
            $fecha = $this->Fecha->toDateString(); // '2025-01-22'
            $fechaCarbon = $this->Fecha->startOfDay(); // Carbon object as timestamp

            // Reabrir clientes registrados ese día
            Cliente::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir proposiciones propuestas ese día (usa FechaPropuesta)
            ProposicionCredito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaPropuesta', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir créditos generados ese día (usa FechaGeneracion)
            Credito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaGeneracion', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir pagos registrados ese día (usa FechaPago)
            Pago::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaPago', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir cuotas creadas ese día (usa FechaCreacion)
            Cuota::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaCreacion', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir análisis económicos creados ese día (usa FechaAnalisis)
            AnalisisEconomico::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaAnalisis', $fecha)
                ->update(['FechaCierre' => null]);

            // Reabrir evaluaciones de crédito creadas ese día (usa FechaRegistro)
            EvaluacionCredito::whereDate('FechaCierre', $fecha)
                ->whereDate('FechaRegistro', $fecha)
                ->update(['FechaCierre' => null]);

            \Illuminate\Support\Facades\Log::info("Día reabierto: {$fecha}", [
                'usuario' => auth()->user()?->name,
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error reabriendo día: ' . $e->getMessage(), [
                'fecha' => $this->Fecha,
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
