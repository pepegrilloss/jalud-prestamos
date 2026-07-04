<?php
/**
 * Corregir creditos: Estatus=SALDADO pero tienen saldo -> pasar a ACTIVO
 *
 * Ejecutar: php corregir_estatus_inconsistentes.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$inconsistentes = DB::select("
    SELECT pc.CodigoCredito, pc.SaldoPendiente, c.CreditoID, c.EstatusCreditoFinal, pc.SedeID
    FROM ProposicionCredito pc
    JOIN Credito c ON pc.ProposicionCreditoID = c.ProposicionCreditoID
    WHERE c.EstatusCreditoFinal = 'SALDADO'
      AND pc.SaldoPendiente > 0
    ORDER BY pc.SedeID
");

echo "=== CORREGIR ESTATUS INCONSISTENTES ===\n";
echo "Encontrados: " . count($inconsistentes) . "\n\n";

if (empty($inconsistentes)) { echo "Nada que corregir.\n"; exit; }

DB::beginTransaction();
$corregidos = 0;

foreach ($inconsistentes as $r) {
    DB::table('Credito')
        ->where('CreditoID', $r->CreditoID)
        ->update([
            'EstatusCreditoFinal' => 'ACTIVO',
            'FechaSaldamiento' => null,
        ]);
    $corregidos++;
}

DB::commit();

echo "  {$corregidos} creditos cambiados de SALDADO -> ACTIVO\n";
echo "\nDone.\n";
