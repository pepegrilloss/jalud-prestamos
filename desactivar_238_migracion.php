<?php
/**
 * DESACTIVA (Activo=0) EXACTAMENTE los 238 creditos de la migracion SICOPA
 * que fueron condonados (Saldo=0, Estatus=SALDADO) pero siguen Activo=1.
 *
 * CRITERIO EXACTO (solo Sede 1 - Chiclayo):
 *   - Credito.Activo = 1
 *   - ProposicionCredito.Activo = 1
 *   - Credito.SedeID = 1
 *   - Credito.EstatusCreditoFinal = 'SALDADO'
 *   - ProposicionCredito.SaldoPendiente = 0
 *   - ProposicionCredito.MontoTotalPagar > SUM(pagos activos no mora)  <- aun deben plata
 *
 * El script ABORTA si el conteo no es exactamente 238.
 * Ejecutar UNA SOLA VEZ: php desactivar_238_migracion.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  DESACTIVAR 238 CREDITOS MIGRACION SICOPA (Sede 1)\n";
echo "  (condonados: Saldo=0, SALDADO, pero siguen Activo=1)\n";
echo "============================================================\n\n";

// ─── 1. Identificar los creditos con el criterio EXACTO ───
$sql = "
    SELECT c.CreditoID, pc.ProposicionCreditoID, pc.CodigoCredito,
           pc.MontoTotal, pc.MontoTotalPagar, pc.SaldoPendiente,
           (SELECT COALESCE(SUM(p.MontoPagado),0)
              FROM pago p
             WHERE p.CreditoID = c.CreditoID AND p.Activo = 1 AND p.EsMora = 0) AS TotalPagado
    FROM Credito c
    JOIN ProposicionCredito pc ON pc.ProposicionCreditoID = c.ProposicionCreditoID
    WHERE c.Activo = 1
      AND pc.Activo = 1
      AND c.SedeID = 1
      AND c.EstatusCreditoFinal = 'SALDADO'
      AND pc.SaldoPendiente = 0
      AND pc.MontoTotalPagar > (SELECT COALESCE(SUM(p.MontoPagado),0)
                                  FROM pago p
                                 WHERE p.CreditoID = c.CreditoID AND p.Activo = 1 AND p.EsMora = 0)
    ORDER BY pc.CodigoCredito
";

$creditos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($creditos);

echo "Creditos encontrados: $total\n";

if ($total !== 238) {
    echo "\n[ABORTADO] El conteo esperado era 238. NO se ejecuto nada.\n";
    echo "Revise antes de continuar.\n";
    exit(1);
}

echo "\nLista de creditos a desactivar:\n";
$sumaMonto = 0;
foreach ($creditos as $c) {
    $sumaMonto += (float)$c['MontoTotal'];
    echo "  {$c['CodigoCredito']} | CreditoID={$c['CreditoID']} | Monto=" . number_format($c['MontoTotal'],2) . " | MFP=" . number_format($c['MontoTotalPagar'],2) . " | Pagado=" . number_format($c['TotalPagado'],2) . "\n";
}
echo "\nTotal Monto de los 238: S/ " . number_format($sumaMonto, 2) . "\n";

echo "\nSe desactivara (Activo=0) en las tablas Credito y ProposicionCredito.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 2. Aplicar cambios en una transaccion ───
$pdo->beginTransaction();
try {
    $idsCredito = array_column($creditos, 'CreditoID');
    $idsProp = array_column($creditos, 'ProposicionCreditoID');
    $inCredito = implode(',', array_map('intval', $idsCredito));
    $inProp = implode(',', array_map('intval', $idsProp));

    $n1 = $pdo->exec("UPDATE Credito SET Activo = 0 WHERE CreditoID IN ($inCredito)");
    $n2 = $pdo->exec("UPDATE ProposicionCredito SET Activo = 0 WHERE ProposicionCreditoID IN ($inProp)");

    if ($n1 !== 238 || $n2 !== 238) {
        throw new \Exception("Conteo inesperado al actualizar: Credito=$n1, Proposicion=$n2");
    }

    // Registrar en logs (1 por credito)
    $stmt = $pdo->prepare("
        INSERT INTO logs (user_id, accion, modelo, modelo_id, old_values, new_values, created_at, SedeID)
        VALUES (0, 'DESACTIVAR_MIGRACION', 'Credito', ?, ?, ?, NOW(), 1)
    ");
    foreach ($creditos as $c) {
        $stmt->execute([
            (int)$c['CreditoID'],
            json_encode(['Activo' => 1, 'EstatusCreditoFinal' => 'SALDADO', 'SaldoPendiente' => (float)$c['SaldoPendiente']]),
            json_encode(['Activo' => 0, 'Motivo' => 'Condonacion migracion SICOPA (saldo 0 sin pagos equivalentes)']),
        ]);
    }

    $pdo->commit();
    echo "\n[OK] $n1 creditos y $n2 proposiciones desactivados correctamente.\n";

} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Transaccion revertida. No se aplico ningun cambio.\n";
    exit(1);
}

// ─── 3. Verificacion final ───
echo "\n=== VERIFICACION ===\n";
$restantes = (int)$pdo->query("SELECT COUNT(*) FROM Credito WHERE CreditoID IN ($inCredito) AND Activo = 1")->fetchColumn();
$activosSede1 = (int)$pdo->query("SELECT COUNT(*) FROM Credito c JOIN ProposicionCredito pc ON pc.ProposicionCreditoID = c.ProposicionCreditoID WHERE c.Activo = 1 AND pc.Activo = 1 AND c.SedeID = 1 AND c.EstatusCreditoFinal = 'SALDADO' AND pc.SaldoPendiente = 0")->fetchColumn();
echo "Creditos 238 aun activos: $restantes (debe ser 0)\n";
echo "Creditos SALDADO+saldo0 aun activos en Sede 1: $activosSede1 (deben quedar solo los correctamente pagados)\n";

echo "\nListo.\n";
