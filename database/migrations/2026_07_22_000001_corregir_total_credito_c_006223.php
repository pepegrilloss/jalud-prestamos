<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $proposicion = DB::table('ProposicionCredito')
                ->where('CodigoCredito', 'C-006223')
                ->where('MontoTotal', 1000)
                ->where('MontoInteres', 50)
                ->where('MontoTotalPagar', 105)
                ->select('ProposicionCreditoID', 'MontoTotal', 'MontoInteres')
                ->lockForUpdate()
                ->first();

            if (!$proposicion) {
                return;
            }

            $credito = DB::table('Credito')
                ->where('ProposicionCreditoID', $proposicion->ProposicionCreditoID)
                ->where('Activo', 1)
                ->select('CreditoID')
                ->lockForUpdate()
                ->first();

            $montoTotalPagar = round((float) $proposicion->MontoTotal + (float) $proposicion->MontoInteres, 2);
            $totalPagado = 0.0;

            if ($credito) {
                DB::table('pago')
                    ->where('CreditoID', $credito->CreditoID)
                    ->where('Activo', 1)
                    ->where('EsMora', 0)
                    ->where('EsPagoAMayor', 1)
                    ->where('EsPagoAMayorPorMora', 0)
                    ->whereNull('SolicitudResolucionID')
                    ->update(['EsPagoAMayor' => 0]);

                $totalPagado = (float) DB::table('pago')
                    ->where('CreditoID', $credito->CreditoID)
                    ->where('Activo', 1)
                    ->where('EsMora', 0)
                    ->sum('MontoPagado');
            }

            $saldoPendiente = max(0, round($montoTotalPagar - $totalPagado, 2));

            DB::table('ProposicionCredito')
                ->where('ProposicionCreditoID', $proposicion->ProposicionCreditoID)
                ->update([
                    'MontoTotalPagar' => $montoTotalPagar,
                    'SaldoPendiente' => $saldoPendiente,
                ]);

            if ($credito && $saldoPendiente > 0) {
                DB::table('Credito')
                    ->where('CreditoID', $credito->CreditoID)
                    ->update([
                        'EstatusCreditoFinal' => 'ACTIVO',
                        'FechaSaldamiento' => null,
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $proposicion = DB::table('ProposicionCredito')
                ->where('CodigoCredito', 'C-006223')
                ->where('MontoTotal', 1000)
                ->where('MontoInteres', 50)
                ->where('MontoTotalPagar', 1050)
                ->select('ProposicionCreditoID')
                ->lockForUpdate()
                ->first();

            if (!$proposicion) {
                return;
            }

            DB::table('ProposicionCredito')
                ->where('ProposicionCreditoID', $proposicion->ProposicionCreditoID)
                ->update([
                    'MontoTotalPagar' => 105,
                    'SaldoPendiente' => 0,
                ]);
        });
    }
};
