<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Cuota;
use Illuminate\Support\Facades\DB;

class CreditoCronogramaService
{
    /**
     * Sincroniza solo las cuotas numeradas. Las filas 0 son referencias de
     * domingos/feriados y nunca deben participar en el conteo de cuotas.
     */
    public static function sincronizarCuotasNumeradas(
        Credito $credito,
        int $numeroCuotas,
        float $montoCuota
    ): array {
        $numeroCuotas = max(1, $numeroCuotas);
        $cronograma = CreditoFechaService::generarCronogramaPorCuotasLaborables(
            $credito->FechaGeneracion,
            $numeroCuotas,
            $credito->SedeID
        );

        $filasDeseadas = collect($cronograma['filas'])
            ->where('NumeroCuota', '>', 0)
            ->keyBy('NumeroCuota');

        $cuotas = Cuota::withoutGlobalScope('sede')
            ->where('CreditoID', $credito->CreditoID)
            ->where('NumeroCuota', '>', 0)
            ->orderBy('NumeroCuota')
            ->orderBy('CuotaID')
            ->lockForUpdate()
            ->get();

        $idsCuotas = $cuotas->pluck('CuotaID');
        $idsConPagos = $idsCuotas->isEmpty()
            ? collect()
            : DB::table('pago')
                ->whereIn('CuotaID', $idsCuotas)
                ->whereNotNull('CuotaID')
                ->pluck('CuotaID')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->flip();

        $creadas = 0;
        $actualizadas = 0;
        $eliminadas = 0;
        $desactivadas = 0;

        foreach ($filasDeseadas as $numero => $fila) {
            $candidatas = $cuotas->where('NumeroCuota', (int) $numero);
            $cuota = $candidatas->firstWhere('Activo', true) ?? $candidatas->first();

            if (! $cuota) {
                $estadoInicial = $credito->EstatusCreditoFinal === 'SALDADO'
                    ? Cuota::ESTADO_PAGADA
                    : Cuota::ESTADO_NORMAL;

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => (int) $numero,
                    'FechaVencimiento' => $fila['FechaVencimiento'],
                    'MontoCuota' => $montoCuota,
                    'Estado' => $estadoInicial,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0,
                    'FechaPago' => null,
                    'FechaCreacion' => now(),
                    'FechaModificacion' => null,
                    'Activo' => true,
                    'SedeID' => $credito->SedeID,
                ]);
                $creadas++;
            } else {
                $cambios = [
                    'FechaVencimiento' => $fila['FechaVencimiento'],
                    'MontoCuota' => $montoCuota,
                    'Activo' => true,
                    'FechaModificacion' => now(),
                ];

                if (
                    $cuota->FechaVencimiento?->toDateString() !== $fila['FechaVencimiento'] ||
                    round((float) $cuota->MontoCuota, 2) !== round($montoCuota, 2) ||
                    ! $cuota->Activo
                ) {
                    $cuota->update($cambios);
                    $actualizadas++;
                }
            }

            foreach ($candidatas->where('CuotaID', '!=', $cuota?->CuotaID) as $duplicada) {
                self::retirarCuotaSobrante($duplicada, $idsConPagos, $eliminadas, $desactivadas);
            }
        }

        foreach ($cuotas->where('NumeroCuota', '>', $numeroCuotas) as $sobrante) {
            self::retirarCuotaSobrante($sobrante, $idsConPagos, $eliminadas, $desactivadas);
        }

        return [
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
            'eliminadas_sin_pago' => $eliminadas,
            'desactivadas_con_pago' => $desactivadas,
        ];
    }

    private static function retirarCuotaSobrante(
        Cuota $cuota,
        $idsConPagos,
        int &$eliminadas,
        int &$desactivadas
    ): void {
        if ($idsConPagos->has((int) $cuota->CuotaID)) {
            if ($cuota->Activo) {
                $cuota->update([
                    'Activo' => false,
                    'FechaModificacion' => now(),
                ]);
                $desactivadas++;
            }

            return;
        }

        $cuota->delete();
        $eliminadas++;
    }
}
