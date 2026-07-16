<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $credito = DB::table('Credito')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('ProposicionCredito.CodigoCredito', 'C-000316')
            ->where('ProposicionCredito.SaldoPendiente', '<=', 0)
            ->select('Credito.CreditoID')
            ->first();

        if (!$credito) {
            return;
        }

        $fechaSaldamiento = DB::table('pago')
            ->where('CreditoID', $credito->CreditoID)
            ->where('Activo', 1)
            ->where('EsMora', 0)
            ->max('FechaPago');

        DB::table('Credito')
            ->where('CreditoID', $credito->CreditoID)
            ->where('EstatusCreditoFinal', '!=', 'SALDADO')
            ->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => $fechaSaldamiento ?: now(),
            ]);
    }

    public function down(): void
    {
        DB::table('Credito')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('ProposicionCredito.CodigoCredito', 'C-000316')
            ->where('ProposicionCredito.SaldoPendiente', '<=', 0)
            ->update([
                'Credito.EstatusCreditoFinal' => 'ACTIVO',
                'Credito.FechaSaldamiento' => null,
            ]);
    }
};
