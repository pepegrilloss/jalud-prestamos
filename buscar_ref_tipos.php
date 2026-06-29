<?php
/**
 * Buscar todos los tipos de credito que contengan "REF"
 * Ejecutar: php buscar_ref_tipos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TIPOS DE CREDITO CON 'REF' (SOLO CHICLAYO) ===\n\n";

$tipos = DB::table('TipoCredito')
    ->where('SedeID', 1)
    ->where(function($q) {
        $q->where('Descripcion', 'like', '%REF%')
          ->orWhere('Descripcion', 'like', '%ref%')
          ->orWhere('Descripcion', 'like', '%Refinanciamiento%');
    })
    ->get();

echo str_pad('ID', 8) . str_pad('Codigo', 12) . str_pad('Descripcion', 35) . "Creditos\n";
echo str_repeat('-', 70) . "\n";

foreach ($tipos as $t) {
    $count = DB::table('ProposicionCredito')
        ->where('TipoCreditoID', $t->TipoCreditoID)
        ->count();
    echo str_pad($t->TipoCreditoID, 8)
       . str_pad($t->Codigo, 12)
       . str_pad("'" . $t->Descripcion . "'", 35)
       . $count . "\n";
}

echo "\nDone.\n";
