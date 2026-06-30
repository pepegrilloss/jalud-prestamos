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
$auto0 = DB::table('pago')
    ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
    ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
    ->where('pago.EsPagoAutomatico', 1)
    ->where('pago.MontoPagado', 0)
    ->where('ProposicionCredito.SedeID', 1)
    ->where('pago.Activo', 1)
    ->select('pago.PagoID', 'pago.MontoPagado', 'pago.FechaPago', 'pago.CreditoID', 'ProposicionCredito.CodigoCredito')
    ->get();

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
        // Calcular monto correcto: MontoTotalPagar - suma de otros pagos
        $mtp = DB::table('ProposicionCredito')
            ->join('Credito', 'ProposicionCredito.ProposicionCreditoID', '=', 'Credito.ProposicionCreditoID')
            ->where('Credito.CreditoID', $a->CreditoID)
            ->value('ProposicionCredito.MontoTotalPagar');
        $otros = DB::table('pago')
            ->where('CreditoID', $a->CreditoID)
            ->where('Activo', 1)
            ->where('PagoID', '!=', $a->PagoID)
            ->sum('MontoPagado');
        $montoCorrecto = max(0, (float)$mtp - (float)$otros);
        DB::table('pago')->where('PagoID', $a->PagoID)->update(['MontoPagado' => $montoCorrecto]);
        echo "  [OK] PagoID={$a->PagoID} ({$a->CodigoCredito}) | Monto 0 → S/ {$montoCorrecto} | MontoTotalPagar=S/{$mtp} | Otros=S/{$otros}\n";
        $pagosFix++;
    }
    echo "  Pagos corregidos: {$pagosFix}\n";
}

echo "\nDone.\n";
