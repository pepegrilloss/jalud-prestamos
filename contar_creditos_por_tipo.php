<?php
/**
 * Contar creditos por tipo
 * Ejecutar: php contar_creditos_por_tipo.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tipos = ['REF.PRICNCIPAL', 'REF.PARALELO', 'REF.SOBREGIRO'];

echo "=== CRÉDITOS POR TIPO ===\n\n";
echo str_pad('Tipo', 25) . str_pad('Cantidad', 10) . str_pad('Activos', 10) . "\n";
echo str_repeat('-', 45) . "\n";

$total = 0;
foreach ($tipos as $tipo) {
    $tc = DB::table('TipoCredito')->where('Descripcion', $tipo)->first();
    if (!$tc) {
        echo str_pad($tipo, 25) . "TipoCredito no encontrado\n";
        continue;
    }

    $count = DB::table('ProposicionCredito')
        ->where('TipoCreditoID', $tc->TipoCreditoID)
        ->where('Activo', 1)
        ->count();

    $activos = DB::table('ProposicionCredito')
        ->where('TipoCreditoID', $tc->TipoCreditoID)
        ->where('Activo', 1)
        ->where('Estado', 'APROBADO')
        ->where('SaldoPendiente', '>', 0)
        ->count();

    echo str_pad($tipo, 25) . str_pad($count, 10) . str_pad($activos, 10) . "\n";
    $total += $count;
}

echo str_repeat('-', 45) . "\n";
echo str_pad('TOTAL', 25) . $total . "\n";
echo "\nDone.\n";
