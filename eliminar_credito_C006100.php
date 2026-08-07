<?php
/**
 * ELIMINA EL CREDITO C-006100 (generado por error)
 * - Desactiva el credito (Activo=0, EstatusCreditoFinal=ELIMINADO)
 * - Desactiva pagos (Activo=0)
 * - Desactiva cuotas (Activo=0)
 * - Elimina moras (DELETE)
 * - Marca la proposicion como eliminada (Eliminado=1)
 * - Registra en logs
 *
 * Solo toca C-006100 (CreditoID=6091, ProposicionCreditoID=6113).
 * Ejecutar UNA SOLA VEZ: php eliminar_credito_C-006100.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$creditoID = 6091;
$propID = 6113;
$codigo = 'C-006100';

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  ELIMINAR CREDITO C-006100 (generado por error)\n";
echo "============================================================\n\n";

// Verificar que el credito existe y coincide
$credito = DB::table('Credito')->where('CreditoID', $creditoID)->first();
$prop = DB::table('ProposicionCredito')->where('ProposicionCreditoID', $propID)->first();

if (!$credito || !$prop) {
    die("[ABORTADO] No se encontro el credito o la proposicion.\n");
}
if ($prop->CodigoCredito !== $codigo) {
    die("[ABORTADO] El CodigoCredito no coincide: {$prop->CodigoCredito} != {$codigo}\n");
}

echo "Credito: {$prop->CodigoCredito} | ClienteID={$prop->ClienteID} | Monto={$prop->MontoTotal}\n";
echo "Pagos activos: " . DB::table('pago')->where('CreditoID', $creditoID)->where('Activo', 1)->count() . "\n";
echo "Cuotas activas: " . DB::table('cuota')->where('CreditoID', $creditoID)->where('Activo', 1)->count() . "\n";
echo "Moras: " . DB::table('mora')->where('CreditoID', $creditoID)->count() . "\n\n";

echo "Se desactivara el credito, sus pagos y cuotas; se eliminaran las moras.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

$pdo->beginTransaction();
try {
    // 1. Marcar proposicion como eliminada
    DB::table('ProposicionCredito')->where('ProposicionCreditoID', $propID)->update([
        'Eliminado' => 1,
        'FechaEliminacion' => now(),
        'UserEliminacionID' => 0,
        'MotivoEliminacion' => 'Credito generado por error - eliminacion solicitada',
        'Activo' => 0,
        'SaldoPendiente' => 0,
    ]);
    echo "[OK] ProposicionCredito marcada como eliminada\n";

    // 2. Marcar credito como inactivo/eliminado
    DB::table('Credito')->where('CreditoID', $creditoID)->update([
        'Activo' => 0,
        'EstatusCreditoFinal' => 'ELIMINADO',
        'FechaSaldamiento' => now(),
    ]);
    echo "[OK] Credito desactivado (ELIMINADO)\n";

    // 3. Desactivar pagos
    $pagos = DB::table('pago')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
    echo "[OK] Pagos desactivados: $pagos\n";

    // 4. Desactivar cuotas
    $cuotas = DB::table('cuota')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
    echo "[OK] Cuotas desactivadas: $cuotas\n";

    // 5. Eliminar moras
    $moras = DB::table('mora')->where('CreditoID', $creditoID)->delete();
    echo "[OK] Moras eliminadas: $moras\n";

    // 6. Registrar en logs
    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'ELIMINAR_CREDITO',
        'modelo' => 'Credito',
        'modelo_id' => $creditoID,
        'old_values' => null,
        'new_values' => json_encode(['codigo' => $codigo, 'motivo' => 'Generado por error', 'Pagos_desactivados' => $pagos, 'Cuotas_desactivadas' => $cuotas, 'Moras_eliminadas' => $moras]),
        'created_at' => now(),
        'SedeID' => $credito->SedeID,
    ]);
    echo "[OK] Log registrado\n";

    $pdo->commit();
    echo "\n[OK] Credito C-006100 eliminado correctamente.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Transaccion revertida. No se aplico ningun cambio.\n";
    exit(1);
}

// Verificacion
echo "\n=== VERIFICACION ===\n";
echo "Credito Activo: " . DB::table('Credito')->where('CreditoID', $creditoID)->value('Activo') . " (debe ser 0)\n";
echo "Pagos activos restantes: " . DB::table('pago')->where('CreditoID', $creditoID)->where('Activo', 1)->count() . " (debe ser 0)\n";
echo "Moras restantes: " . DB::table('mora')->where('CreditoID', $creditoID)->count() . " (debe ser 0)\n";
echo "Proposicion Eliminado: " . DB::table('ProposicionCredito')->where('ProposicionCreditoID', $propID)->value('Eliminado') . " (debe ser 1)\n";

echo "\nListo.\n";
