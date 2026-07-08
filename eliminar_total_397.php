<?php
/**
 * ELIMINAR TOTALMENTE el credito C-000397 (sin rastro)
 * Ejecutar: php eliminar_total_397.php
 */
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$prop = DB::table('ProposicionCredito')->where('CodigoCredito', 'C-000397')->first();
if (!$prop) { echo "No encontrado\n"; exit; }

$credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
$creditoID = $credito->CreditoID ?? null;

echo "=== ELIMINAR TOTALMENTE C-000397 ===\n\n";
DB::beginTransaction();

if ($creditoID) {
    $n = DB::table('pago')->where('CreditoID', $creditoID)->delete();
    echo "  Pagos eliminados: {$n}\n";
    $n = DB::table('cuota')->where('CreditoID', $creditoID)->delete();
    echo "  Cuotas eliminadas: {$n}\n";
    $n = DB::table('mora')->where('CreditoID', $creditoID)->delete();
    echo "  Mora eliminada: {$n}\n";
    DB::table('Credito')->where('CreditoID', $creditoID)->delete();
    echo "  Credito eliminado\n";
}

// Aprobaciones vinculadas
$n = DB::table('AprobacionProposicion')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->delete();
echo "  Aprobaciones eliminadas: {$n}\n";

DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->delete();
echo "  ProposicionCredito eliminada\n";

DB::commit();
echo "\nDone. C-000397 eliminado completamente.\n";
