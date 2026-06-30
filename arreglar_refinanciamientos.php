<?php
/**
 * Diagnosticar y corregir creditos refinanciados inconsistentes
 * Ejecutar: php arreglar_refinanciamientos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== ARREGLAR REFINANCIAMIENTOS INCONSISTENTES ===\n\n";

// 1. Diagnosticar C-004820
echo "--- C-004820 ---\n";
$prop = DB::select("SELECT * FROM ProposicionCredito WHERE CodigoCredito = ?", ['C-004820'])[0] ?? null;
if (!$prop) { echo "No encontrado\n"; exit; }

echo "  EsRefi={$prop->EsRefinanciamiento} | AntID=".($prop->ProposicionCreditoAnteriorID??'NULL')."\n";
echo "  FueRefi={$prop->FueRefinanciada} | Saldo={$prop->SaldoPendiente} | Activo={$prop->Activo}\n";

$cred = DB::select("SELECT * FROM Credito WHERE ProposicionCreditoID = ?", [$prop->ProposicionCreditoID])[0] ?? null;
echo "  EstatusCredito={$cred->EstatusCreditoFinal}\n";

// Buscar el nuevo credito que lo refinancia
$nuevo = DB::select("SELECT pc.CodigoCredito, pc.ProposicionCreditoID, pc.SaldoPendiente, pc.EsRefinanciamiento, pc.ProposicionCreditoAnteriorID, c.CreditoID, c.EstatusCreditoFinal FROM ProposicionCredito pc LEFT JOIN Credito c ON pc.ProposicionCreditoID=c.ProposicionCreditoID WHERE pc.ProposicionCreditoAnteriorID = ? AND pc.Activo = 1", [$prop->ProposicionCreditoID]);
if ($nuevo) {
    $n = $nuevo[0];
    echo "\n  Credito nuevo que lo refinancia: {$n->CodigoCredito} (ID={$n->ProposicionCreditoID}) | EsRefi={$n->EsRefinanciamiento} | Saldo={$n->SaldoPendiente} | EstCr={$n->EstatusCreditoFinal}\n";
}

// 2. Buscar TODOS los creditos con FueRefi=1 pero Saldo != 0
echo "\n=== CREDITOS FueRefi=1 CON Saldo != 0 ===\n";
$inconsistentes = DB::select("SELECT pc.CodigoCredito, pc.ProposicionCreditoID, pc.SaldoPendiente, pc.FueRefinanciada, pc.Activo, c.CreditoID, c.EstatusCreditoFinal FROM ProposicionCredito pc LEFT JOIN Credito c ON pc.ProposicionCreditoID=c.ProposicionCreditoID WHERE pc.SedeID = 1 AND pc.FueRefinanciada = 1 AND pc.SaldoPendiente != 0");

echo "Encontrados: ".count($inconsistentes)."\n";
foreach ($inconsistentes as $inc) {
    echo "  {$inc->CodigoCredito} | Saldo={$inc->SaldoPendiente} | Activo={$inc->Activo} | EstCr={$inc->EstatusCreditoFinal}\n";
}

// 3. Buscar creditos con auto-pago de 0
echo "\n=== PAGOS AUTOMATICOS CON MONTO 0 ===\n";
$auto0 = DB::select("SELECT p.PagoID, p.MontoPagado, p.FechaPago, p.CreditoID, pc.CodigoCredito FROM pago p JOIN Credito c ON p.CreditoID=c.CreditoID JOIN ProposicionCredito pc ON c.ProposicionCreditoID=pc.ProposicionCreditoID WHERE p.EsPagoAutomatico = 1 AND p.MontoPagado = 0 AND pc.SedeID = 1 AND p.Activo = 1");

echo "Encontrados: ".count($auto0)."\n";
foreach ($auto0 as $a) {
    echo "  PagoID={$a->PagoID} | {$a->CodigoCredito} | Monto={$a->MontoPagado} | Fecha={$a->FechaPago}\n";
}

// 4. CORREGIR
echo "\n=== CORRIGIENDO ===\n";
$totalCorregidos = 0;

foreach ($inconsistentes as $inc) {
    $saldoAnt = (float)$inc->SaldoPendiente;

    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $inc->ProposicionCreditoID)
        ->update(['SaldoPendiente' => 0]);

    if ($inc->EstatusCreditoFinal !== 'SALDADO' && $inc->CreditoID) {
        DB::table('Credito')
            ->where('CreditoID', $inc->CreditoID)
            ->update(['EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => now()]);
    }

    echo "  [OK] {$inc->CodigoCredito} | Saldo {$saldoAnt} → 0\n";
    $totalCorregidos++;
}

if ($totalCorregidos == 0) {
    echo "  Nada que corregir\n";
}

// 5. Corregir pagos automaticos con monto 0
echo "\n=== CORRIGIENDO PAGOS AUTOMATICOS EN 0 ===\n";
if (count($auto0) == 0) {
    echo "  Nada que corregir\n";
} else {
    $pagosFix = 0;
    foreach ($auto0 as $a) {
        // Calcular el monto correcto: MontoTotalPagar - suma de otros pagos
        $totales = DB::select("SELECT pc.MontoTotalPagar, COALESCE(SUM(p2.MontoPagado),0) as otros_pagos FROM ProposicionCredito pc JOIN Credito c ON pc.ProposicionCreditoID=c.ProposicionCreditoID LEFT JOIN pago p2 ON c.CreditoID=p2.CreditoID AND p2.Activo=1 AND p2.PagoID != ? WHERE c.CreditoID = ?", [$a->PagoID, $a->CreditoID]);
        $montoCorrecto = 0;
        if ($totales) {
            $montoCorrecto = max(0, (float)($totales[0]->MontoTotalPagar ?? 0) - (float)($totales[0]->otros_pagos ?? 0));
        }
        DB::table('pago')->where('PagoID', $a->PagoID)->update(['MontoPagado' => $montoCorrecto]);
        echo "  [OK] PagoID={$a->PagoID} ({$a->CodigoCredito}) | Monto 0 → {$montoCorrecto}\n";
        $pagosFix++;
    }
    echo "  Pagos corregidos: {$pagosFix}\n";
}

echo "\nDone.\n";
