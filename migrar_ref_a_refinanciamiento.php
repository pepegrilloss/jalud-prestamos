<?php
/**
 * Eliminar tipos REF.* y REFINANCIACION, reasignar creditos a Refinanciamiento
 * Solo Chiclayo (SedeID=1)
 *
 * Ejecutar: php migrar_ref_a_refinanciamiento.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

set_time_limit(0);
ini_set('memory_limit', '512M');

$SEDE_ID = 1;

echo "=== ELIMINAR REF.* Y REASIGNAR A Refinanciamiento (CHICLAYO) ===\n\n";

// 1. Buscar Refinanciamiento en Chiclayo
$refi = DB::table('TipoCredito')
    ->where('Descripcion', 'Refinanciamiento')
    ->where('SedeID', $SEDE_ID)
    ->first();

if (!$refi) {
    $ultimoCodigo = DB::table('TipoCredito')->orderByDesc('TipoCreditoID')->value('Codigo');
    $nuevoNum = $ultimoCodigo ? intval(substr($ultimoCodigo, 1)) + 1 : 999;
    $codigo = 'C' . $nuevoNum;

    $refiId = DB::table('TipoCredito')->insertGetId([
        'Codigo' => $codigo,
        'Descripcion' => 'Refinanciamiento',
        'Activo' => 1,
        'SedeID' => $SEDE_ID,
        'FechaCreacion' => now(),
    ]);
    $refi = DB::table('TipoCredito')->where('TipoCreditoID', $refiId)->first();
    echo "[+] Creado 'Refinanciamiento' (ID={$refi->TipoCreditoID})\n\n";
}

// 2. Buscar TODOS los tipos a eliminar (cualquier variante)
$tiposObsoletos = DB::table('TipoCredito')
    ->where('SedeID', $SEDE_ID)
    ->where(function($q) {
        $q->where('Descripcion', 'like', 'REF%')
          ->orWhere('Descripcion', 'REFINANCIACION');
    })
    ->where('Descripcion', '!=', 'Refinanciamiento')
    ->get();

echo "--- TIPOS A ELIMINAR ---\n";
$totalCreditos = 0;
foreach ($tiposObsoletos as $t) {
    $count = DB::table('ProposicionCredito')->where('TipoCreditoID', $t->TipoCreditoID)->count();
    echo "  ID={$t->TipoCreditoID} | '{$t->Descripcion}' | {$count} creditos\n";
    $totalCreditos += $count;
}

if ($tiposObsoletos->isEmpty()) {
    echo "  No se encontraron tipos REF.* para eliminar en Chiclayo.\n";
    echo "\nDone.\n";
    exit(0);
}

echo "\nTotal creditos a reasignar: {$totalCreditos}\n";

// 3. Reasignar y eliminar
echo "\n--- EJECUTANDO ---\n";
$reasignados = 0;
$eliminados = 0;

foreach ($tiposObsoletos as $tipo) {
    if ($tipo->TipoCreditoID == $refi->TipoCreditoID) continue;

    $n = DB::table('ProposicionCredito')
        ->where('TipoCreditoID', $tipo->TipoCreditoID)
        ->update(['TipoCreditoID' => $refi->TipoCreditoID]);

    $restantes = DB::table('ProposicionCredito')
        ->where('TipoCreditoID', $tipo->TipoCreditoID)
        ->count();

    if ($restantes > 0) {
        echo "  [!!] Quedan {$restantes} refs a '{$tipo->Descripcion}' - FK no permite borrar\n";
        continue;
    }

    try {
        DB::table('TipoCredito')->where('TipoCreditoID', $tipo->TipoCreditoID)->delete();
        echo "  [OK] '{$tipo->Descripcion}': {$n} creditos -> Refinanciamiento | ELIMINADO\n";
        $reasignados += $n;
        $eliminados++;
    } catch (\Exception $e) {
        echo "  [!!] No se pudo eliminar '{$tipo->Descripcion}': {$e->getMessage()}\n";
    }
}

// 4. Verificar
echo "\n=== RESUMEN ===\n";
echo "  Reasignados: {$reasignados}\n";
echo "  Eliminados:  {$eliminados}\n";

$quedan = DB::table('TipoCredito')
    ->where('SedeID', $SEDE_ID)
    ->where(function($q) {
        $q->where('Descripcion', 'like', 'REF%')
          ->orWhere('Descripcion', 'REFINANCIACION');
    })
    ->where('Descripcion', '!=', 'Refinanciamiento')
    ->count();

if ($quedan > 0) {
    echo "  [!!] AUN QUEDAN {$quedan} tipos REF.*\n";
} else {
    echo "  [OK] Todos eliminados\n";
}

echo "\nDone.\n";
