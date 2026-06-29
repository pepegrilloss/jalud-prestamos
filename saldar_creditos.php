<?php
/**
 * Saldar creditos especificos (SaldoPendiente = 0, Estatus = SALDADO)
 * Chiclayo
 *
 * Ejecutar: php saldar_creditos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

set_time_limit(0);
ini_set('memory_limit', '256M');

$codigos = [
    'C-001028', 'C-000486', 'C-001208', 'C-001725', 'C-001291',
    'C-001914', 'C-000570', 'C-002406', 'C-002411', 'C-002219',
];

echo "=== SALDAR 10 CREDITOS (CHICLAYO) ===\n\n";

DB::beginTransaction();

$saldados = 0;
$cuotasAct = 0;
$totalSaldo = 0;

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

    if ($saldoAnt <= 0) {
        echo "  [i]  {$codigo} -> Ya esta saldado (Saldo=0)\n";
        continue;
    }

    $totalSaldo += $saldoAnt;

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
            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
            ->update([
                'Estado' => 'PAGADA',
                'FechaPago' => now(),
            ]);
        $cuotasAct += $n;
    }

    $saldados++;
    echo "  [OK] {$codigo} -> Saldo S/{$saldoAnt} saldado\n";
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Creditos saldados: {$saldados}\n";
echo "  Saldo total saldado: S/ " . number_format($totalSaldo, 2) . "\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
