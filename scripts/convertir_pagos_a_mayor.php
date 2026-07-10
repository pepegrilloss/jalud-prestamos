<?php
/**
 * Convierte los pagos del credito C-003603 del 12/06/2026 al 22/06/2026 a "pago a mayor".
 * Solo ejecutar una vez.
 */

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'jvcso1ub_jalud_prestamos';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR de conexion: " . $e->getMessage() . "\n");
}

$codigo = 'C-003603';
$desde  = '2026-06-12';
$hasta  = '2026-06-22';

$stmt = $pdo->prepare("
    SELECT c.CreditoID FROM Credito c
    JOIN ProposicionCredito pc ON pc.ProposicionCreditoID = c.ProposicionCreditoID
    WHERE pc.CodigoCredito = ?
");
$stmt->execute([$codigo]);
$creditoId = $stmt->fetchColumn();

if (!$creditoId) {
    die("ERROR: No se encontro el credito $codigo\n");
}

echo "Credito: $codigo (CreditoID: $creditoId)\n";
echo "Rango: $desde al $hasta\n\n";

echo "--- ANTES ---\n";
$stmt = $pdo->prepare("
    SELECT PagoID, FechaPago, MontoPagado, TipoConcepto, EsPagoAMayor
    FROM pago
    WHERE CreditoID = ? AND FechaPago >= ? AND FechaPago < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY FechaPago
");
$stmt->execute([$creditoId, $desde, $hasta]);
foreach ($stmt->fetchAll() as $p) {
    $f = substr($p['FechaPago'], 0, 10);
    echo "  PagoID={$p['PagoID']} | $f | Monto={$p['MontoPagado']} | Concepto={$p['TipoConcepto']} | A_MAYOR={$p['EsPagoAMayor']}\n";
}

$stmtUpdate = $pdo->prepare("
    UPDATE pago
    SET TipoConcepto = 'C', EsPagoAMayor = 1, EsPagoForzado = 0
    WHERE CreditoID = ? AND FechaPago >= ? AND FechaPago < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmtUpdate->execute([$creditoId, $desde, $hasta]);
echo "\nActualizados: {$stmtUpdate->rowCount()} pagos\n\n";

echo "--- DESPUES ---\n";
$stmt->execute([$creditoId, $desde, $hasta]);
foreach ($stmt->fetchAll() as $p) {
    $f = substr($p['FechaPago'], 0, 10);
    echo "  PagoID={$p['PagoID']} | $f | Monto={$p['MontoPagado']} | Concepto={$p['TipoConcepto']} | A_MAYOR={$p['EsPagoAMayor']}\n";
}

echo "\nListo.\n";
