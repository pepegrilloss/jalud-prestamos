<?php
/**
 * CORRIGE C-006313 (BUENO CAMACHO JANETH ELIZABETH).
 *
 * El credito (Gen 13-07-2026, 21 cuotas x S/25, Tasa 5%, Sede 1) se genero
 * con el calendario VIEJO que no saltaba los dias no laborables
 * (27-28-29-jul y 06-ago), por lo que sus cuotas quedaron 4 dias antes.
 *
 * Correccion (confirmada):
 *   1. FechaVencimiento del credito: 07-08 -> 11-08 (martes)
 *   2. Cuotas PENDIENTES afectadas (las pagadas NO se tocan):
 *        #12: 27-07 -> 30-07
 *        #13: 28-07 -> 31-07
 *        #14: 29-07 -> 01-08
 *        #17: 01-08 -> 05-08
 *        #21: 06-08 -> 11-08
 *
 * IDEMPOTENTE. Ejecutar: php corregir_C006313.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR C-006313 (vencimiento 11-08 + cuotas pendientes)\n";
echo "============================================================\n\n";

// ─── 1. Verificar estado actual ───
$c = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.CodigoCredito', 'C-006313')
    ->select('c.CreditoID', 'c.FechaGeneracion', 'c.FechaVencimiento', 'c.SedeID', 'pc.NumeroCuotas', 'pc.SaldoPendiente')
    ->first();

if (!$c) {
    echo "[ERROR] No existe C-006313.\n";
    exit(1);
}

echo "CreditoID={$c->CreditoID} | Gen=" . substr($c->FechaGeneracion,0,10) . " | Venc=" . substr($c->FechaVencimiento,0,10) . " | Saldo={$c->SaldoPendiente}\n\n";

$correcciones = [
    12 => '2026-07-30',
    13 => '2026-07-31',
    14 => '2026-08-01',
    17 => '2026-08-05',
    21 => '2026-08-11',
];

echo "Cuotas pendientes a corregir:\n";
$pendientes = DB::table('cuota')->where('CreditoID', $c->CreditoID)
    ->where('NumeroCuota', '>', 0)
    ->where('Activo', 1)
    ->whereNotIn('Estado', ['PAGADA', 'PAGADO'])
    ->orderBy('NumeroCuota')
    ->get(['CuotaID', 'NumeroCuota', 'Estado', 'FechaVencimiento']);

$aCorregir = [];
foreach ($pendientes as $q) {
    $n = (int) $q->NumeroCuota;
    if (isset($correcciones[$n])) {
        $actual = substr($q->FechaVencimiento, 0, 10);
        echo "  #{$n} (CuotaID={$q->CuotaID}) | {$q->Estado} | {$actual} -> {$correcciones[$n]}\n";
        if ($actual !== $correcciones[$n]) {
            $aCorregir[] = ['cuota_id' => (int) $q->CuotaID, 'numero' => $n, 'old' => $actual, 'new' => $correcciones[$n]];
        }
    }
}

$vencActual = substr($c->FechaVencimiento, 0, 10);
echo "\nFechaVencimiento credito: {$vencActual} -> 2026-08-11\n";
if ($vencActual === '2026-08-11') {
    echo "(ya correcto)\n";
}

if (count($aCorregir) === 0 && $vencActual === '2026-08-11') {
    echo "\nNada que corregir (ya esta bien).\n";
    exit(0);
}

echo "\nSe aplicaran " . count($aCorregir) . " correcciones de cuotas + vencimiento.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 2. Aplicar ───
$pdo->beginTransaction();
try {
    $ahora = now();

    // 2a. Vencimiento del credito
    if ($vencActual !== '2026-08-11') {
        DB::table('Credito')->where('CreditoID', $c->CreditoID)
            ->update(['FechaVencimiento' => '2026-08-11 00:00:00']);
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_VENC',
            'modelo' => 'Credito',
            'modelo_id' => $c->CreditoID,
            'old_values' => json_encode(['FechaVencimiento' => $vencActual]),
            'new_values' => json_encode(['FechaVencimiento' => '2026-08-11', 'Motivo' => 'C-006313 calendario viejo no salto 27-29-jul ni 06-ago (revision manual especial +4d)']),
            'created_at' => $ahora,
            'SedeID' => $c->SedeID,
        ]);
        echo "  [OK] FechaVencimiento -> 2026-08-11\n";
    }

    // 2b. Cuotas pendientes
    foreach ($aCorregir as $corr) {
        DB::table('cuota')->where('CuotaID', $corr['cuota_id'])
            ->update(['FechaVencimiento' => $corr['new']]);
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_CUOTA',
            'modelo' => 'Cuota',
            'modelo_id' => $corr['cuota_id'],
            'old_values' => json_encode(['FechaVencimiento' => $corr['old'], 'NumeroCuota' => $corr['numero']]),
            'new_values' => json_encode(['FechaVencimiento' => $corr['new'], 'Motivo' => 'C-006313 calendario viejo (27-29-jul y 06-ago)']),
            'created_at' => $ahora,
            'SedeID' => $c->SedeID,
        ]);
        echo "  [OK] Cuota #{$corr['numero']} -> {$corr['new']}\n";
    }

    $pdo->commit();
    echo "\nAPLICADO CORRECTAMENTE.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// ─── 3. Verificacion ───
echo "\n=== VERIFICACION ===\n";
$c2 = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.CodigoCredito', 'C-006313')
    ->select('c.CreditoID', 'c.FechaVencimiento')
    ->first();
echo "  FechaVencimiento = " . substr($c2->FechaVencimiento,0,10) . " (esperado 2026-08-11)\n";

$cuotas = DB::table('cuota')->where('CreditoID', $c->CreditoID)->where('NumeroCuota', '>', 0)->where('Activo', 1)
    ->whereNotIn('Estado', ['PAGADA', 'PAGADO'])
    ->orderBy('NumeroCuota')
    ->get(['NumeroCuota', 'Estado', 'FechaVencimiento']);
foreach ($cuotas as $q) {
    $n = (int) $q->NumeroCuota;
    $esperada = $correcciones[$n] ?? 'sin cambio';
    $marca = substr($q->FechaVencimiento, 0, 10) === $esperada ? 'OK' : 'REVISAR';
    echo "  #{$q->NumeroCuota} | {$q->Estado} | " . substr($q->FechaVencimiento,0,10) . " (esperada: $esperada) $marca\n";
}

echo "\n============================================================\n";
echo "  COMPLETADO\n";
echo "============================================================\n";
