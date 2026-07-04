<?php
/**
 * Eliminar mora registrada de 5 creditos - sin afectar caja
 * Ejecutar: php limpiar_mora_5.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$codigos = ['C-002124', 'C-001927', 'C-001128', 'C-000918', 'C-000786'];

echo "=== ELIMINAR MORA DE 5 CREDITOS ===\n\n";
DB::beginTransaction();

foreach ($codigos as $codigo) {
    $prop = DB::table('ProposicionCredito')->where('CodigoCredito', $codigo)->where('SedeID', 1)->first();
    if (!$prop) { echo "  [??] {$codigo} -> No encontrado\n"; continue; }

    $credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
    if (!$credito) { echo "  [??] {$codigo} -> Sin credito\n"; continue; }

    // Eliminar registros de mora del credito
    $n = DB::table('mora')->where('CreditoID', $credito->CreditoID)->delete();
    echo "  [OK] {$codigo} -> {$n} registro(s) de mora eliminado(s)\n";
}

DB::commit();
echo "\nDone.\n";
