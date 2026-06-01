<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Pago;
use App\Models\ProposicionCredito;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado para cálculo y sincronización de SaldoPendiente.
 * 
 * REGLA: La columna ProposicionCredito.SaldoPendiente es la fuente de verdad.
 * Se actualiza SOLO cuando se registra, anula o borra un pago.
 * Las lecturas NUNCA recalculan; leen directamente la columna.
 */
class SaldoPendienteService
{
    /**
     * Recalcular y guardar el saldo pendiente de una proposición específica.
     * Solo debe llamarse desde observers/jobs, NUNCA desde lecturas.
     */
    public static function recalcular(int $proposicionCreditoID): float
    {
        $credito = Credito::withoutGlobalScope('sede')
            ->withoutEagerLoads()
            ->where('ProposicionCreditoID', $proposicionCreditoID)
            ->where('Activo', true)
            ->first();

        if (!$credito) {
            // Sin crédito activo → saldo = 0
            DB::table('ProposicionCredito')
                ->where('ProposicionCreditoID', $proposicionCreditoID)
                ->update(['SaldoPendiente' => 0]);
            return 0;
        }

        // Calcular total pagado (1 query)
        $totalPagado = (float) (DB::selectOne("
            SELECT COALESCE((
                SELECT SUM(p.MontoPagado) 
                FROM pago p 
                WHERE p.CreditoID = ? 
                  AND p.Activo = 1
                  AND (p.EstadoTraslado IS NULL OR p.EstadoTraslado != 'TRASLADADO')
                  AND p.EsMora = 0
            ), 0) as total_pagado
        ", [$credito->CreditoID])->total_pagado ?? 0);

        // La deuda real es MontoTotalPagar, no SUM(cuota.MontoCuota)
        // Las cuotas son referenciales; sus montos pueden no cerrar exacto por redondeo
        $proposicion = ProposicionCredito::withoutGlobalScope('sede')
            ->withoutEagerLoads()
            ->where('ProposicionCreditoID', $proposicionCreditoID)
            ->first();

        $saldo = max(0, (float)($proposicion->MontoTotalPagar ?? 0) - $totalPagado);
        $saldoAnterior = (float) ($proposicion->SaldoPendiente ?? 0);

        // Actualización directa sin disparar model events (evita recursión)
        DB::table('ProposicionCredito')
            ->where('ProposicionCreditoID', $proposicionCreditoID)
            ->update(['SaldoPendiente' => $saldo]);

        if ($saldoAnterior != $saldo) {
            \App\Models\Log::registrar(
                'ACTUALIZAR',
                'ProposicionCredito',
                $proposicionCreditoID,
                ['SaldoPendiente' => $saldoAnterior],
                ['SaldoPendiente' => $saldo]
            );
        }

        return (float) $saldo;
    }

    /**
     * Leer el saldo pendiente de la columna (sin recalcular).
     * Este es el método que debe usarse para lecturas en UI/filtros/formularios.
     */
    public static function obtener(int $proposicionCreditoID): float
    {
        $saldo = DB::table('ProposicionCredito')
            ->where('ProposicionCreditoID', $proposicionCreditoID)
            ->value('SaldoPendiente');

        return (float) ($saldo ?? 0);
    }

    /**
     * Sincronizar todos los saldos pendientes (para mantenimiento/backfill).
     * Ejecutar con: php artisan saldos:sincronizar
     */
    public static function sincronizarTodos(): int
    {
        $proposiciones = DB::table('ProposicionCredito')
            ->where('Activo', true)
            ->pluck('ProposicionCreditoID');

        $actualizados = 0;

        foreach ($proposiciones as $id) {
            self::recalcular($id);
            $actualizados++;
        }

        return $actualizados;
    }

    /**
     * Obtener créditos con saldo para un cliente (para formulario de pagos).
     * Reemplaza la lógica duplicada 5+ veces en PagoResource.
     */
    public static function obtenerCreditosConSaldoParaCliente(
        int $clienteID,
        ?int $zonaID = null,
        bool $puedePagarMayor = false
    ): \Illuminate\Support\Collection {
        $query = Credito::with(['proposicion.tipoCredito'])
            ->whereHas('proposicion', function ($q) use ($clienteID, $zonaID, $puedePagarMayor) {
                $q->where('ClienteID', $clienteID);

                if (!$puedePagarMayor) {
                    $q->where('FueRefinanciada', 0)
                      ->where('Activo', true);
                } else {
                    $q->where(function ($sub) {
                        $sub->where('Activo', true)
                            ->orWhere('FueRefinanciada', 1);
                    });
                }

                if ($zonaID) {
                    $q->where('ZonaID', $zonaID);
                }
            })
            ->where('Activo', 1);

        if (!$puedePagarMayor) {
            $query->whereNotIn('EstatusCreditoFinal', ['SALDADO', 'REFINANCIADO']);
        }

        return $query->get()->filter(function ($credito) use ($puedePagarMayor) {
            if (!$credito->proposicion) {
                return false;
            }
            // Leer la columna directamente (sin recalcular)
            $saldo = (float) ($credito->proposicion->SaldoPendiente ?? 0);
            return $saldo > 0 || ($puedePagarMayor && in_array($credito->EstatusCreditoFinal, ['SALDADO', 'REFINANCIADO']));
        });
    }
}
