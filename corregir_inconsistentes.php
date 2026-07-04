<?php
/**
 * Corregir creditos inconsistentes: Estatus=SALDADO pero Saldo>0
 *
 * Ejecutar: php corregir_inconsistentes.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== CORREGIR CREDITOS INCONSISTENTES ===\n\n";

$inconsistentes = DB::select("
    SELECT pc.ProposicionCreditoID, pc.CodigoCredito, pc.SaldoPendiente, c.CreditoID, c.EstatusCreditoFinal, pc.SedeID
    FROM ProposicionCredito pc
    JOIN Credito c ON pc.ProposicionCreditoID = c.ProposicionCreditoID
    WHERE c.EstatusCreditoFinal = 'SALDADO'
      AND pc.SaldoPendiente > 0
    ORDER BY pc.SedeID
");

echo "Encontrados: " . count($inconsistentes) . "\n\n";

if (empty($inconsistentes)) { echo "Nada que corregir.\n"; exit; }

DB::beginTransaction();
$corregidos = 0;
$cuotasAct = 0;

foreach ($inconsistentes as $r) {
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $r->ProposicionCreditoID)
        ->update(['SaldoPendiente' => 0]);

    $n = DB::table('cuota')
        ->where('CreditoID', $r->CreditoID)
        ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
        ->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);

    $cuotasAct += $n;
    $corregidos++;
}

DB::commit();

echo "  Corregidos: {$corregidos}\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
