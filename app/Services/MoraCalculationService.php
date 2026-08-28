<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Mora;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MoraCalculationService
{
    public function procesarCreditoHasta(
        Credito $credito,
        Carbon|string $hasta,
        Carbon|string|null $desde = null,
    ): array {
        $hasta = $this->normalizarFecha($hasta);
        $inicio = Carbon::parse($credito->FechaVencimiento)->startOfDay()->addDay();

        if ($desde !== null) {
            $inicio = $inicio->max($this->normalizarFecha($desde));
        }

        if ($inicio->gt($hasta)) {
            return ['creadas' => 0, 'omitidas' => 0, 'monto' => 0.0];
        }

        $proposicion = DB::table('ProposicionCredito')
            ->where('ProposicionCreditoID', $credito->ProposicionCreditoID)
            ->first(['ClienteID', 'MontoTotalPagar', 'TasaMora']);

        if (! $proposicion) {
            return ['creadas' => 0, 'omitidas' => 0, 'monto' => 0.0];
        }

        $porcentaje = $this->resolverPorcentaje(
            (float) ($proposicion->TasaMora ?? 0),
            (int) $proposicion->ClienteID,
        );

        if ($porcentaje <= 0) {
            return ['creadas' => 0, 'omitidas' => 0, 'monto' => 0.0];
        }

        $creadas = 0;
        $omitidas = 0;
        $montoTotal = 0.0;

        DB::transaction(function () use (
            $credito,
            $inicio,
            $hasta,
            $porcentaje,
            $proposicion,
            &$creadas,
            &$omitidas,
            &$montoTotal,
        ) {
            DB::table('Credito')
                ->where('CreditoID', $credito->CreditoID)
                ->lockForUpdate()
                ->first();

            $existentes = DB::table('mora')
                ->where('CreditoID', $credito->CreditoID)
                ->whereDate('FechaMora', '>=', $inicio->toDateString())
                ->whereDate('FechaMora', '<=', $hasta->toDateString())
                ->pluck('FechaMora')
                ->mapWithKeys(fn ($fecha) => [Carbon::parse($fecha)->toDateString() => true])
                ->all();

            for ($fecha = $inicio->copy(); $fecha->lte($hasta); $fecha->addDay()) {
                if (isset($existentes[$fecha->toDateString()])) {
                    $omitidas++;

                    continue;
                }

                $saldo = $this->saldoAlInicioDelDia(
                    (int) $credito->CreditoID,
                    (float) $proposicion->MontoTotalPagar,
                    $fecha,
                );

                if ($saldo <= 0) {
                    $omitidas++;

                    continue;
                }

                $monto = round($saldo * ($porcentaje / 100), 2);

                Mora::create([
                    'CreditoID' => $credito->CreditoID,
                    'FechaMora' => $fecha->toDateString(),
                    'SaldoPendiente' => $saldo,
                    'PorcentajeMora' => $porcentaje,
                    'MontoMora' => $monto,
                    'MoraAcumulada' => 0,
                    'SedeID' => $credito->SedeID,
                ]);

                $creadas++;
                $montoTotal = round($montoTotal + $monto, 2);
            }

            if ($creadas > 0) {
                $this->recalcularAcumulado((int) $credito->CreditoID);
            }
        });

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'monto' => $montoTotal,
        ];
    }

    private function resolverPorcentaje(float $tasaCredito, int $clienteId): float
    {
        if ($tasaCredito > 0) {
            return $tasaCredito;
        }

        return (float) (DB::table('Cliente as cl')
            ->leftJoin('TasaMora as tm', 'tm.TasaMoraID', '=', 'cl.TasaMoraID')
            ->where('cl.ClienteID', $clienteId)
            ->value('tm.Porcentaje') ?? 0);
    }

    private function saldoAlInicioDelDia(int $creditoId, float $montoTotal, Carbon $fecha): float
    {
        $pagado = (float) DB::table('pago')
            ->where('CreditoID', $creditoId)
            ->where('Activo', true)
            ->where('EsMora', false)
            ->where('FechaPago', '<', $fecha->copy()->startOfDay())
            ->sum('MontoPagado');

        $montoRetirado = (float) DB::table('solicitudes_resolucion_excedente as sre')
            ->join('pago as pago_origen', 'pago_origen.PagoID', '=', 'sre.PagoOrigenID')
            ->where('pago_origen.CreditoID', $creditoId)
            ->whereIn('sre.TipoResolucion', ['TRASLADO_DE_PAGO', 'APLICACION_PAGO_MAYOR'])
            ->where('sre.Estado', 'APROBADA')
            ->whereRaw(
                'COALESCE(sre.FechaCierre, sre.updated_at, sre.created_at) < ?',
                [$fecha->copy()->startOfDay()->toDateTimeString()],
            )
            ->sum('sre.MontoAplicar');

        $pagoNeto = max(0, $pagado - $montoRetirado);

        return round(max(0, $montoTotal - $pagoNeto), 2);
    }

    private function recalcularAcumulado(int $creditoId): void
    {
        $acumulado = 0.0;

        DB::table('mora')
            ->where('CreditoID', $creditoId)
            ->orderBy('FechaMora')
            ->orderBy('MoraID')
            ->get(['MoraID', 'MontoMora'])
            ->each(function ($mora) use (&$acumulado) {
                $acumulado = round($acumulado + (float) $mora->MontoMora, 2);

                DB::table('mora')
                    ->where('MoraID', $mora->MoraID)
                    ->update(['MoraAcumulada' => $acumulado]);
            });
    }

    private function normalizarFecha(Carbon|string $fecha): Carbon
    {
        return $fecha instanceof Carbon
            ? $fecha->copy()->startOfDay()
            : Carbon::parse($fecha)->startOfDay();
    }
}
