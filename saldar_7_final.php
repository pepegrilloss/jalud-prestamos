<?php
/**
 * Saldar 7 creditos y eliminar su mora - Chiclayo
 *
 * Ejecutar: php saldar_7_final.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$codigos = ['C-000720', 'C-000998', 'C-000910', 'C-001594', 'C-002075', 'C-002087', 'C-002202'];

echo "=== SALDAR 7 CREDITOS + ELIMINAR MORA ===\n\n";
DB::beginTransaction();

$saldados = 0; $noEncontrados = 0; $morasElim = 0; $cuotasAct = 0;

foreach ($codigos as $codigo) {
    $prop = DB::table('ProposicionCredito')->where('CodigoCredito', $codigo)->where('SedeID', 1)->first();
    if (!$prop) { echo "  [??] {$codigo} -> No encontrado\n"; $noEncontrados++; continue; }

    // Saldar
    $saldoAnt = (float)$prop->SaldoPendiente;
    DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->update(['SaldoPendiente' => 0]);

    $credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
    if ($credito) {
        DB::table('Credito')->where('CreditoID', $credito->CreditoID)->update([
            'EstatusCreditoFinal' => 'SALDADO',
            'FechaSaldamiento' => now(),
        ]);

        $n = DB::table('cuota')->where('CreditoID', $credito->CreditoID)
            ->whereIn('Estado', ['PENDIENTE','NORMAL','MORA','VENCIDA'])
            ->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
        $cuotasAct += $n;

        // Eliminar mora
        $m = DB::table('mora')->where('CreditoID', $credito->CreditoID)->delete();
        $morasElim += $m;
    }

    $saldados++;
    echo "  [OK] {$codigo} | Saldo S/{$saldoAnt} -> SALDADO | Mora eliminada\n";
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Saldados:        {$saldados}\n";
echo "  No encontrados:  {$noEncontrados}\n";
echo "  Cuotas act.:     {$cuotasAct}\n";
echo "  Registros mora eliminados: {$morasElim}\n\nDone.\n";
