<?php
/**
 * Corregir creditos inconsistentes: Saldo=0 pero Status=ACTIVO
 * Chiclayo + Trujillo
 *
 * Ejecutar: php corregir_creditos_saldados.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CORREGIR CREDITOS SALDADOS INCONSISTENTES (CHICLAYO + TRUJILLO) ===\n\n";

$pendientes = DB::table('credito')
    ->join('proposicioncredito', 'credito.ProposicionCreditoID', '=', 'proposicioncredito.ProposicionCreditoID')
    ->where('credito.Activo', 1)
    ->whereIn('credito.SedeID', [1, 2])
    ->where('credito.EstatusCreditoFinal', 'ACTIVO')
    ->where('proposicioncredito.SaldoPendiente', 0)
    ->select(
        'credito.CreditoID',
        'credito.SedeID',
        'proposicioncredito.CodigoCredito',
        'proposicioncredito.ProposicionCreditoID',
        'proposicioncredito.FueRefinanciada',
        'proposicioncredito.EsRefinanciamiento',
        'proposicioncredito.MontoTotalPagar'
    )
    ->get();

echo "Encontrados: " . count($pendientes) . " creditos inconsistentes\n\n";

if ($pendientes->isEmpty()) {
    echo "Nada que corregir.\n";
    exit(0);
}

$corregidos = 0;
foreach ($pendientes as $c) {
    $sedeNombre = $c->SedeID == 1 ? 'Chiclayo' : 'Trujillo';
    $razon = '';
    if ($c->FueRefinanciada == 1) {
        $razon = 'FueRefinanciada=1';
    } elseif ($c->EsRefinanciamiento == 1) {
        $razon = 'EsRefinanciamiento=1';
    } else {
        $razon = 'Saldo=0';
    }

    DB::table('credito')
        ->where('CreditoID', $c->CreditoID)
        ->update([
            'EstatusCreditoFinal' => 'SALDADO',
            'FechaSaldamiento' => now(),
        ]);

    $corregidos++;
    echo "  [OK] [{$sedeNombre}] {$c->CodigoCredito} -> SALDADO ({$razon})\n";
}

echo "\n--- ACTUALIZANDO CUOTAS PENDIENTES ---\n";
$cuotasAct = 0;
foreach ($pendientes as $c) {
    $sedeNombre = $c->SedeID == 1 ? 'Chiclayo' : 'Trujillo';
    $n = DB::table('cuota')
        ->where('CreditoID', $c->CreditoID)
        ->where('Estado', 'PENDIENTE')
        ->update([
            'Estado' => 'PAGADA',
            'FechaPago' => now(),
        ]);
    if ($n > 0) {
        echo "  [OK] [{$sedeNombre}] {$c->CodigoCredito}: {$n} cuota(s) -> PAGADA\n";
        $cuotasAct += $n;
    }
}

echo "\n=== RESUMEN ===\n";
echo "  Creditos corregidos: {$corregidos}\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
