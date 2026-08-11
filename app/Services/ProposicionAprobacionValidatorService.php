<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoCredito;

/**
 * Valida restricciones de negocio en el momento de APROBAR una proposicion.
 *
 * Divide las alertas en dos niveles:
 *   - BLOQUEANTES: impiden aprobar (credito activo mismo tipo, cliente observado).
 *   - ADVERTENCIAS: permiten aprobar SOLO con confirmacion extra del aprobador
 *     (mora pendiente, cliente no al dia, proposiciones pendientes, MMR superado).
 *
 * Regla de negocio clave: un cliente NO puede tener 2 creditos ACTIVOS del mismo
 * tipo, EXCEPTO cuando el tipo es "Cuenta Paralelo" o es refinanciamiento.
 */
class ProposicionAprobacionValidatorService
{
    /**
     * Retorna ['bloqueantes' => string[], 'advertencias' => string[]]
     */
    public function obtenerAlertas(ProposicionCredito $proposicion): array
    {
        $bloqueantes = [];
        $advertencias = [];

        // ── BLOQUEANTE: credito ACTIVO del mismo tipo (excepto Paralelo / refinanciamiento) ──
        if (! $proposicion->EsRefinanciamiento && ! $this->esCuentaParalelo($proposicion)) {
            $creditoActivo = $this->creditoActivoMismoTipo($proposicion);
            if ($creditoActivo) {
                $bloqueantes[] = 'El cliente ya tiene un crédito activo (' . $creditoActivo . ') del mismo tipo. Para otorgar uno nuevo debe refinanciarlo o saldar el anterior.';
            }
        }

        // ── BLOQUEANTE: cliente OBSERVADO ──
        if ($proposicion->cliente && $proposicion->cliente->Estado === 'OBSERVADO') {
            $bloqueantes[] = 'El cliente se encuentra OBSERVADO. No se puede aprobar la proposición.';
        }

        // ── ADVERTENCIA: mora pendiente en el mismo tipo de credito ──
        $moraPendiente = $this->moraPendienteMismoTipo($proposicion);
        if ($moraPendiente->isNotEmpty()) {
            $advertencias[] = 'El cliente tiene mora pendiente en el mismo tipo de crédito: ' . $moraPendiente->implode(', ');
        }

        // ── ADVERTENCIA: cliente no esta al dia en sus cuotas ──
        if (! ProposicionValidatorService::clienteEstaAlDiaEnSusCuotas($proposicion->ClienteID)) {
            $advertencias[] = 'El cliente no está al día en el pago de sus cuotas.';
        }

        // ── ADVERTENCIA: cliente con 2+ proposiciones pendientes (excluye esta) ──
        $pendientes = ProposicionCredito::where('ClienteID', $proposicion->ClienteID)
            ->where('Activo', true)
            ->whereNotIn('Estado', ['APROBADO', 'RECHAZADO'])
            ->where('ProposicionCreditoID', '!=', $proposicion->ProposicionCreditoID)
            ->count();

        if ($pendientes >= 2) {
            $advertencias[] = 'El cliente ya tiene ' . $pendientes . ' proposiciones pendientes (máximo permitido: 2).';
        }

        // ── ADVERTENCIA: monto supera el MMR ──
        $montoDisponible = ProposicionValidatorService::calcularMontoDisponible($proposicion->ClienteID);
        if ($montoDisponible['montoMaximoRecomendado'] > 0 && (float) $proposicion->MontoTotal > $montoDisponible['montoMaximoRecomendado']) {
            $advertencias[] = 'El monto solicitado (S/ ' . number_format((float) $proposicion->MontoTotal, 2) . ') supera el MMR del cliente (S/ ' . number_format($montoDisponible['montoMaximoRecomendado'], 2) . ').';
        }

        return [
            'bloqueantes' => $bloqueantes,
            'advertencias' => $advertencias,
        ];
    }

    public function tieneBloqueantes(ProposicionCredito $proposicion): bool
    {
        return count($this->obtenerAlertas($proposicion)['bloqueantes']) > 0;
    }

    public function tieneAdvertencias(ProposicionCredito $proposicion): bool
    {
        return count($this->obtenerAlertas($proposicion)['advertencias']) > 0;
    }

    /**
     * Cuenta Paralelo: TipoCredito con Descripcion 'Cuenta Paralelo' (puede existir por sede).
     */
    private function esCuentaParalelo(ProposicionCredito $proposicion): bool
    {
        $tipo = TipoCredito::find($proposicion->TipoCreditoID);
        return $tipo && strtolower(trim($tipo->Descripcion ?? '')) === 'cuenta paralelo';
    }

    /**
     * Busca un credito ACTIVO del cliente con el mismo tipo de credito (excluye esta proposicion).
     * Retorna el CodigoCredito o null.
     */
    private function creditoActivoMismoTipo(ProposicionCredito $proposicion): ?string
    {
        return ProposicionCredito::where('ClienteID', $proposicion->ClienteID)
            ->where('TipoCreditoID', $proposicion->TipoCreditoID)
            ->where('Activo', true)
            ->where('FueRefinanciada', 0)
            ->where('SaldoPendiente', '>', 0.009)
            ->where('ProposicionCreditoID', '!=', $proposicion->ProposicionCreditoID)
            ->whereHas('credito', function ($q) {
                $q->where('Activo', true)
                  ->where('EstatusCreditoFinal', 'ACTIVO');
            })
            ->value('CodigoCredito');
    }

    /**
     * Mora pendiente en creditos del mismo tipo (misma logica que GenerarCreditoResource).
     */
    private function moraPendienteMismoTipo(ProposicionCredito $proposicion): \Illuminate\Support\Collection
    {
        return ProposicionCredito::where('ClienteID', $proposicion->ClienteID)
            ->where('SedeID', $proposicion->SedeID)
            ->where('TipoCreditoID', $proposicion->TipoCreditoID)
            ->where('Activo', true)
            ->where('Estado', 'APROBADO')
            ->where('FueRefinanciada', 0)
            ->where('ProposicionCreditoID', '!=', $proposicion->ProposicionCreditoID)
            ->whereHas('credito', function ($q) {
                $q->where('Activo', true)
                    ->whereHas('moras', function ($sub) {
                        $sub->whereRaw('MoraAcumulada > COALESCE(
                          (SELECT SUM(p.MontoPagado) FROM pago p
                           WHERE p.CreditoID = mora.CreditoID
                             AND (p.TipoConcepto = ? OR p.EsMora = 1)
                             AND p.Activo = 1), 0
                      )', ['M']);
                    });
            })
            ->pluck('CodigoCredito');
    }
}
