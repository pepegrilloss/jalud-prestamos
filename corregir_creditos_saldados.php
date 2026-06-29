<?php
/**
 * Corregir creditos inconsistentes: Saldo=0 pero Status=ACTIVO
 * Solo Chiclayo (SedeID=1)
 *
 * Ejecutar: php corregir_creditos_saldados.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CORREGIR CREDITOS SALDADOS INCONSISTENTES (CHICLAYO + TRUJILLO) ===\n\n";

// Buscar creditos con Status=ACTIVO pero Saldo=0 (ambas sedes)
$pendientes = DB::table('Credito')
    ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
    ->where('Credito.Activo', 1)
    ->whereIn('Credito.SedeID', [1, 2])
    ->where('Credito.EstatusCreditoFinal', 'ACTIVO')
    ->where('ProposicionCredito.SaldoPendiente', 0)
    ->select(
        'Credito.CreditoID',
        'Credito.SedeID',
        'ProposicionCredito.CodigoCredito',
        'ProposicionCredito.ProposicionCreditoID',
        'ProposicionCredito.FueRefinanciada',
        'ProposicionCredito.EsRefinanciamiento',
        'ProposicionCredito.MontoTotalPagar'
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

    DB::table('Credito')
        ->where('CreditoID', $c->CreditoID)
        ->update([
            'EstatusCreditoFinal' => 'SALDADO',
            'FechaSaldamiento' => now(),
        ]);

    $corregidos++;
    echo "  [OK] [{$sedeNombre}] {$c->CodigoCredito} -> SALDADO ({$razon})\n";
}

// Marcar cuotas pendientes como PAGADA para estos creditos
echo "\n--- ACTUALIZANDO CUOTAS PENDIENTES ---\n";
$cuotasAct = 0;
foreach ($pendientes as $c) {
    $sedeNombre = $c->SedeID == 1 ? 'Chiclayo' : 'Trujillo';
    $n = DB::table('Cuota')
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
