<?php
/**
 * Saldar 5 creditos - Chiclayo
 *
 * Ejecutar: php saldar_5_nuevos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$codigos = ['C-002124', 'C-001927', 'C-001128', 'C-000918', 'C-000786'];

echo "=== SALDAR 5 CREDITOS (CHICLAYO) ===\n\n";

DB::beginTransaction();
$saldados = 0; $cuotasAct = 0; $noEncontrados = 0;

foreach ($codigos as $codigo) {
    $prop = DB::table('ProposicionCredito')
        ->where('CodigoCredito', $codigo)
        ->where('SedeID', 1)
        ->first();

    if (!$prop) { echo "  [??] {$codigo} -> No encontrado\n"; $noEncontrados++; continue; }
    if ((float)$prop->SaldoPendiente <= 0) { echo "  [i]  {$codigo} -> Ya saldado\n"; continue; }

    DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->update(['SaldoPendiente' => 0]);

    $credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
    if ($credito) {
        DB::table('Credito')->where('CreditoID', $credito->CreditoID)->update(['EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => now()]);
        $n = DB::table('cuota')->where('CreditoID', $credito->CreditoID)->whereIn('Estado', ['PENDIENTE','NORMAL','MORA','VENCIDA'])->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
        $cuotasAct += $n;
    }
    echo "  [OK] {$codigo}\n";
    $saldados++;
}

DB::commit();
echo "\n=== RESUMEN ===\n";
echo "  Saldados:        {$saldados}\n";
echo "  No encontrados:  {$noEncontrados}\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n\nDone.\n";
