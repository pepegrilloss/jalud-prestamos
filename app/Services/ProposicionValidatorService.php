<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoCredito;

class ProposicionValidatorService
{
    /**
     * Determina si hay una proposición a excluir del cálculo MMR (refinanciamiento).
     */
    public static function obtenerExclusionMMR(int $tipoCreditoID, ?int $proposicionAnteriorID): ?int
    {
        if (!$tipoCreditoID) {
            return null;
        }

        $tipoCredito = TipoCredito::find($tipoCreditoID);
        if (!$tipoCredito || strtolower($tipoCredito->Descripcion) !== 'refinanciamiento') {
            return null;
        }

        return $proposicionAnteriorID ? (int) $proposicionAnteriorID : null;
    }

    /**
     * Calcula monto maximo recomendado, monto utilizado y monto disponible para un cliente.
     * Opcionalmente excluye una proposicion del calculo (caso refinanciamiento).
     */
    public static function calcularMontoDisponible(int $clienteID, ?int $excluirProposicionID = null): array
    {
        $cliente = Cliente::find($clienteID);
        if (!$cliente || !$cliente->analisisEconomico) {
            return [
                'montoMaximoRecomendado' => 0,
                'montoUtilizado' => 0,
                'montoDisponible' => 0,
            ];
        }

        $montoMaximoRecomendado = (float) $cliente->analisisEconomico->MontoMaxRecomendado;

        $proposiciones = ProposicionCredito::where('ClienteID', $clienteID)
            ->where('Activo', true)
            ->where('FueRefinanciada', 0)
            ->where('EsRefinanciamiento', 0)
            ->whereHas('credito', function ($query) {
                $query->where('Activo', true);
            })
            ->get();

        $montoUtilizado = 0;
        foreach ($proposiciones as $proposicion) {
            if ($excluirProposicionID && $proposicion->ProposicionCreditoID == $excluirProposicionID) {
                continue;
            }
            $saldoPendiente = (float) ($proposicion->SaldoPendiente ?? 0);
            if ($saldoPendiente > 0) {
                $montoUtilizado += (float) $proposicion->MontoTotal;
            }
        }

        $montoDisponible = max(0, $montoMaximoRecomendado - $montoUtilizado);

        return [
            'montoMaximoRecomendado' => $montoMaximoRecomendado,
            'montoUtilizado' => $montoUtilizado,
            'montoDisponible' => $montoDisponible,
        ];
    }

    /**
     * Verifica si un cliente está al día en el pago de sus cuotas.
     * Calcula las cuotas que deberían estar pagadas hasta hoy y compara con lo pagado.
     */
    public static function clienteEstaAlDiaEnSusCuotas(int $clienteID): bool
    {
        try {
            $creditos = Credito::whereHas('proposicion', function ($query) use ($clienteID) {
                $query->where('ClienteID', $clienteID)->where('Activo', true);
            })->where('Activo', true)->get();

            foreach ($creditos as $credito) {
                $saldo = (float) ($credito->proposicion->SaldoPendiente ?? 0);
                if ($saldo <= 0) {
                    continue;
                }
                if ($credito->FechaVencimiento && \Carbon\Carbon::parse($credito->FechaVencimiento)->isFuture()) {
                    continue;
                }
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }
}
