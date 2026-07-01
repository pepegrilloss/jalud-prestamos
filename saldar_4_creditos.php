<?php
/**
 * Saldar 4 creditos especificos - Chiclayo
 *
 * Ejecutar: php saldar_4_creditos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$codigos = ['C-000431', 'C-002181', 'C-002386', 'C-002235'];

$saldos = [
    'C-000431' => 10250,
    'C-002181' => 43,
    'C-002386' => 365,
    'C-002235' => 448,
];

echo "=== SALDAR 4 CREDITOS (CHICLAYO) ===\n\n";

DB::beginTransaction();

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

    $montoCerrar = $saldos[$codigo];

    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->update(['SaldoPendiente' => 0]);

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
