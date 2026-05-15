<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración de índices de rendimiento para las tablas transaccionales más consultadas.
 * Estos índices aceleran:
 * - Listados de PagoResource y CreditoResource (filtros por sede, fecha, estado)
 * - Cálculo de SaldoPendiente (joins entre pago, cuota, credito)
 * - Widgets de dashboard (sumas por mes, sede, zona)
 * - Global Scope BelongsToSede (filtro por SedeID en cada query)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── TABLA: pago ────────────────────────────────────────
        // Índice principal para calcularSaldoPendiente (el cuello de botella #1)
        if (!$this->indexExists('pago', 'idx_pago_credito_activo_fecha')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->index(['CreditoID', 'Activo', 'FechaPago'], 'idx_pago_credito_activo_fecha');
            });
        }

        // Para getMontoPagadoAttribute en Cuota (SUM por CuotaID)
        if (!$this->indexExists('pago', 'idx_pago_cuota_activo')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->index(['CuotaID', 'Activo'], 'idx_pago_cuota_activo');
            });
        }

        // Para filtros de PagoResource por sede y fecha
        if (!$this->indexExists('pago', 'idx_pago_sede_fecha_activo')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->index(['SedeID', 'FechaPago', 'Activo'], 'idx_pago_sede_fecha_activo');
            });
        }

        // Para el filtro de modifyQueryUsing en PagoResource (pagos automáticos)
        if (!$this->indexExists('pago', 'idx_pago_automatico_concepto')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->index(['EsPagoAutomatico', 'TipoConcepto'], 'idx_pago_automatico_concepto');
            });
        }

        // ─── TABLA: cuota ───────────────────────────────────────
        // Para listados de cuotas por crédito (ordenadas por número)
        if (!$this->indexExists('cuota', 'idx_cuota_credito_activo_numero')) {
            Schema::table('cuota', function (Blueprint $table) {
                $table->index(['CreditoID', 'Activo', 'NumeroCuota'], 'idx_cuota_credito_activo_numero');
            });
        }

        // Para búsqueda de cuota pendiente del día
        if (!$this->indexExists('cuota', 'idx_cuota_credito_activo_estado')) {
            Schema::table('cuota', function (Blueprint $table) {
                $table->index(['CreditoID', 'Activo', 'Estado'], 'idx_cuota_credito_activo_estado');
            });
        }

        // Para búsqueda de cuota por fecha de vencimiento
        if (!$this->indexExists('cuota', 'idx_cuota_credito_activo_vencimiento')) {
            Schema::table('cuota', function (Blueprint $table) {
                $table->index(['CreditoID', 'Activo', 'FechaVencimiento'], 'idx_cuota_credito_activo_vencimiento');
            });
        }

        // ─── TABLA: Credito ─────────────────────────────────────
        // Para calcularSaldoPendiente y relaciones con ProposicionCredito
        if (!$this->indexExists('Credito', 'idx_credito_proposicion_activo')) {
            Schema::table('Credito', function (Blueprint $table) {
                $table->index(['ProposicionCreditoID', 'Activo'], 'idx_credito_proposicion_activo');
            });
        }

        // Para widgets y filtros por sede
        if (!$this->indexExists('Credito', 'idx_credito_sede_activo_fecha')) {
            Schema::table('Credito', function (Blueprint $table) {
                $table->index(['SedeID', 'Activo', 'FechaGeneracion'], 'idx_credito_sede_activo_fecha');
            });
        }

        // ─── TABLA: ProposicionCredito ──────────────────────────
        // Para formulario de pagos (créditos por cliente con saldo)
        if (!$this->indexExists('ProposicionCredito', 'idx_proposicion_cliente_activo_refinanciada')) {
            Schema::table('ProposicionCredito', function (Blueprint $table) {
                $table->index(['ClienteID', 'Activo', 'FueRefinanciada'], 'idx_proposicion_cliente_activo_refinanciada');
            });
        }

        // Para listados filtrados por estado
        if (!$this->indexExists('ProposicionCredito', 'idx_proposicion_estado_activo')) {
            Schema::table('ProposicionCredito', function (Blueprint $table) {
                $table->index(['Estado', 'Activo'], 'idx_proposicion_estado_activo');
            });
        }

        // Para filtro rápido por SaldoPendiente > 0
        if (!$this->indexExists('ProposicionCredito', 'idx_proposicion_saldo_pendiente')) {
            Schema::table('ProposicionCredito', function (Blueprint $table) {
                $table->index(['SaldoPendiente'], 'idx_proposicion_saldo_pendiente');
            });
        }

        // ─── TABLA: apertura_cierre_dia ─────────────────────────
        // Para estaAbierto() que se llama en cada operación
        if (!$this->indexExists('apertura_cierre_dia', 'idx_apertura_estado_fecha')) {
            Schema::table('apertura_cierre_dia', function (Blueprint $table) {
                $table->index(['EstadoDia', 'Fecha'], 'idx_apertura_estado_fecha');
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'pago' => ['idx_pago_credito_activo_fecha', 'idx_pago_cuota_activo', 'idx_pago_sede_fecha_activo', 'idx_pago_automatico_concepto'],
            'cuota' => ['idx_cuota_credito_activo_numero', 'idx_cuota_credito_activo_estado', 'idx_cuota_credito_activo_vencimiento'],
            'Credito' => ['idx_credito_proposicion_activo', 'idx_credito_sede_activo_fecha'],
            'ProposicionCredito' => ['idx_proposicion_cliente_activo_refinanciada', 'idx_proposicion_estado_activo', 'idx_proposicion_saldo_pendiente'],
            'apertura_cierre_dia' => ['idx_apertura_estado_fecha'],
        ];

        foreach ($indexes as $table => $indexNames) {
            foreach ($indexNames as $indexName) {
                if ($this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Verificar si un índice existe antes de crearlo/eliminarlo (MySQL)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($result) > 0;
    }
};
