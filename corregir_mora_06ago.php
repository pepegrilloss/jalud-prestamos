<?php
/**
 * CORRECCION MORA INCORRECTA - FERIADO 06-AGO-2026 (Batalla de Junin)
 *
 * Contexto: el 06-ago-2026 es feriado nacional (Batalla de Junin) que la API
 * anterior (Nager.Date) NO detectaba. El job de mora del 07-ago genero mora
 * incorrecta para los creditos con vencimiento el 06-ago, porque calculaba el
 * vencimiento efectivo como 06-ago (no lo veia como feriado).
 *
 * Con Calendarific: siguienteDiaLaborable(06-ago) = 07-ago (hoy).
 * Regla del job: si fecha <= vencimientoEfectivo, NO se genera mora.
 * => La mora del 07-ago en esos creditos es INCORRECTA y debe eliminarse.
 *
 * Ademas, se extiende FechaVencimiento al siguiente dia laborable (07-ago)
 * para consistencia con reportes y validaciones de cliente al dia.
 *
 * SOLO toca creditos con FechaVencimiento = 2026-08-06 y saldo pendiente > 0.
 * Ejecutar UNA SOLA VEZ: php corregir_mora_06ago.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\CalendarioLaboralService;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORRECCION MORA INCORRECTA - FERIADO 06-AGO-2026\n";
echo "============================================================\n\n";

// ─── 1. Identificar creditos afectados (venc 06-ago, saldo > 0) ───
$creditos = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->join('Cliente as cl', 'cl.ClienteID', '=', 'pc.ClienteID')
    ->where('c.Activo', 1)
    ->where('pc.Activo', 1)
    ->whereDate('c.FechaVencimiento', '2026-08-06')
    ->where('pc.SaldoPendiente', '>', 0)
    ->select('c.CreditoID', 'pc.CodigoCredito', 'cl.NombresApellidos', 'pc.SaldoPendiente', 'c.SedeID')
    ->orderBy('c.CreditoID')
    ->get();

echo "Creditos con vencimiento 06-ago-2026 y saldo pendiente: " . count($creditos) . "\n";
foreach ($creditos as $c) {
    echo "  {$c->CodigoCredito} | ID={$c->CreditoID} | {$c->NombresApellidos} | Saldo=" . number_format($c->SaldoPendiente, 2) . " | Sede={$c->SedeID}\n";
}

if ($creditos->isEmpty()) {
    echo "\n[ABORTADO] No hay creditos que corregir.\n";
    exit(1);
}

$ids = $creditos->pluck('CreditoID')->toArray();
$in = implode(',', array_map('intval', $ids));

// ─── 2. Mostrar mora incorrecta del 07-ago ───
$morasIncorrectas = DB::table('mora')
    ->whereIn('CreditoID', $ids)
    ->whereDate('FechaMora', '2026-08-07')
    ->get();

echo "\nMora INCORRECTA del 07-ago-2026 encontrada: " . count($morasIncorrectas) . " registros\n";
foreach ($morasIncorrectas as $m) {
    $cod = $creditos->firstWhere('CreditoID', $m->CreditoID)->CodigoCredito ?? $m->CreditoID;
    echo "  {$cod} | MoraID={$m->MoraID} | Monto=" . number_format($m->MontoMora, 2) . " | Acum=" . number_format($m->MoraAcumulada, 2) . "\n";
}

echo "\nSe eliminara esa mora y se extendera FechaVencimiento a 07-ago-2026.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 3. Aplicar cambios ───
$pdo->beginTransaction();
try {
    // 3a. Eliminar mora incorrecta del 07-ago
    $eliminadas = DB::table('mora')
        ->whereIn('CreditoID', $ids)
        ->whereDate('FechaMora', '2026-08-07')
        ->delete();
    echo "\n[OK] Mora eliminada: $eliminadas registro(s)\n";

    // 3b. Extender FechaVencimiento al siguiente dia laborable (07-ago)
    $actualizados = 0;
    foreach ($creditos as $c) {
        $nuevaFecha = CalendarioLaboralService::siguienteDiaLaborable('2026-08-06', $c->SedeID)->format('Y-m-d');
        DB::table('Credito')->where('CreditoID', $c->CreditoID)
            ->update(['FechaVencimiento' => $nuevaFecha]);
        $actualizados++;
    }
    echo "[OK] FechaVencimiento extendida a 07-ago-2026: $actualizados credito(s)\n";

    // 3c. Registrar en logs
    foreach ($creditos as $c) {
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'MORA_FERIADO',
            'modelo' => 'Credito',
            'modelo_id' => $c->CreditoID,
            'old_values' => json_encode(['FechaMora' => '2026-08-07', 'FechaVencimiento' => '2026-08-06']),
            'new_values' => json_encode(['Mora_07ago_eliminada' => true, 'FechaVencimiento' => '2026-08-07', 'Motivo' => 'Feriado Batalla de Junin (Calendarific)']),
            'created_at' => now(),
            'SedeID' => $c->SedeID,
        ]);
    }
    echo "[OK] Logs registrados\n";

    $pdo->commit();
    echo "\n[OK] Correccion aplicada correctamente.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Transaccion revertida. No se aplico ningun cambio.\n";
    exit(1);
}

// ─── 4. Verificacion ───
echo "\n=== VERIFICACION ===\n";
$restantes = DB::table('mora')->whereIn('CreditoID', $ids)->whereDate('FechaMora', '2026-08-07')->count();
echo "Mora 07-ago restante en creditos corregidos: $restantes (debe ser 0)\n";

$nuevasFechas = DB::table('Credito')->whereIn('CreditoID', $ids)->pluck('FechaVencimiento');
$todas07 = $nuevasFechas->every(fn ($f) => substr($f, 0, 10) === '2026-08-07');
echo "FechaVencimiento actualizada a 07-ago en todos: " . ($todas07 ? 'SI' : 'NO - REVISAR') . "\n";
foreach ($creditos as $c) {
    $f = $nuevasFechas[$c->CreditoID] ?? null;
    echo "  {$c->CodigoCredito}: venc = " . ($f ? substr($f, 0, 10) : 'N/A') . "\n";
}

echo "\nListo.\n";
