<?php
/**
 * Saldar 5 creditos especificos - Chiclayo
 *
 * Ejecutar: php saldar_5_creditos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$codigos = ['C-001935', 'C-002312', 'C-002346', 'C-001070', 'C-002115'];

echo "=== SALDAR 5 CREDITOS (CHICLAYO) ===\n\n";

DB::beginTransaction();

$saldos = [
    'C-001935' => 1084,
    'C-002312' => 500,
    'C-002346' => 35,
    'C-001070' => 353,
    'C-002115' => 178.50,
];

$total = 0;
$cuotasAct = 0;

foreach ($codigos as $codigo) {
    $prop = DB::table('ProposicionCredito')
        ->where('CodigoCredito', $codigo)
        ->where('SedeID', 1)
        ->first();

    if (!$prop) {
        echo "  [??] {$codigo} -> No encontrado en Chiclayo\n";
        continue;
    }

    $saldoAnt = (float) $prop->SaldoPendiente;
    $montoCerrar = $saldos[$codigo] ?? $saldoAnt;

    // 1. Poner saldo en 0
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->update(['SaldoPendiente' => 0]);

    // 2. Marcar credito como SALDADO
    $credito = DB::table('Credito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->first();

    if ($credito) {
        DB::table('Credito')
            ->where('CreditoID', $credito->CreditoID)
            ->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => now(),
            ]);

        // 3. Marcar cuotas pendientes como PAGADA
        $n = DB::table('cuota')
            ->where('CreditoID', $credito->CreditoID)
            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
            ->update([
                'Estado' => 'PAGADA',
                'FechaPago' => now(),
            ]);
        $cuotasAct += $n;
    }

    $total += $montoCerrar;
    echo "  [OK] {$codigo} | S/ {$montoCerrar} -> SALDADO\n";
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Creditos saldados: " . count($codigos) . "\n";
echo "  Total saldado: S/ " . number_format($total, 2) . "\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
