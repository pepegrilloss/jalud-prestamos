<?php
/**
 * Saldar 2 creditos por DNI - Chiclayo
 *
 * Ejecutar: php saldar_2_x_dni.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== SALDAR 2 CREDITOS POR DNI ===\n\n";

DB::beginTransaction();

$saldados = 0;
$cuotasAct = 0;

// DNI=10542858 -> C-002067 -> S/180
$cli = DB::table('Cliente')->where('DNI', 10542858)->where('SedeID', 1)->first();
if ($cli) {
    $prop = DB::table('ProposicionCredito')->where('CodigoCredito', 'C-002067')->where('SedeID', 1)->first();
    if ($prop) {
        $credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
        DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->update(['SaldoPendiente' => 0]);
        if ($credito) {
            DB::table('Credito')->where('CreditoID', $credito->CreditoID)->update(['EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => now()]);
            $n = DB::table('cuota')->where('CreditoID', $credito->CreditoID)->whereIn('Estado', ['PENDIENTE','NORMAL','MORA','VENCIDA'])->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
            $cuotasAct += $n;
        }
        echo "  [OK] C-002067 ({$cli->NombresApellidos}) | S/180 -> SALDADO\n";
        $saldados++;
    }
}

// DNI=76835452 -> C-002483 -> S/30
$cli2 = DB::table('Cliente')->where('DNI', 76835452)->where('SedeID', 1)->first();
if ($cli2) {
    $prop2 = DB::table('ProposicionCredito')->where('CodigoCredito', 'C-002483')->where('SedeID', 1)->first();
    if ($prop2) {
        $credito2 = DB::table('Credito')->where('ProposicionCreditoID', $prop2->ProposicionCreditoID)->first();
        DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop2->ProposicionCreditoID)->update(['SaldoPendiente' => 0]);
        if ($credito2) {
            DB::table('Credito')->where('CreditoID', $credito2->CreditoID)->update(['EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => now()]);
            $n = DB::table('cuota')->where('CreditoID', $credito2->CreditoID)->whereIn('Estado', ['PENDIENTE','NORMAL','MORA','VENCIDA'])->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
            $cuotasAct += $n;
        }
        echo "  [OK] C-002483 ({$cli2->NombresApellidos}) | S/30 -> SALDADO\n";
        $saldados++;
    }
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Saldados: {$saldados}\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
