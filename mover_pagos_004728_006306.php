<?php
/**
 * Mueve 5 pagos de C-004728 a C-006306 y cambia cliente.
 * Ejecutar UNA SOLA VEZ: php mover_pagos_004728_006306.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "========================================\n";
echo "  MOVER PAGOS: C-004728 -> C-006306\n";
echo "========================================\n\n";

// 1. Cambiar cliente de C-006306
$pdo->exec("UPDATE ProposicionCredito SET ClienteID = 757 WHERE CodigoCredito = 'C-006306'");
echo "[OK] C-006306: ClienteID cambiado a 757 (BARRANTES ANGELES PAULA)\n";

// 2. Mover 5 pagos a C-006306 y quitar A MAYOR
$pagos = [116683, 117261, 118398, 118886, 119497];
$suma = 0;
foreach ($pagos as $pid) {
    $stmt = $pdo->prepare("UPDATE pago SET CreditoID = 6308, EsPagoAMayor = 0 WHERE PagoID = ?");
    $stmt->execute([$pid]);
    $monto = $pdo->query("SELECT MontoPagado FROM pago WHERE PagoID = $pid")->fetchColumn();
    $suma += $monto;
    echo "[OK] PagoID=$pid: movido a CreditoID=6308, EsPagoAMayor=0, S/ $monto\n";
}
echo "  Total movido: S/ $suma\n\n";

// 3. Recalcular C-004728
$tp4728 = (float) $pdo->query("SELECT COALESCE(SUM(MontoPagado),0) FROM pago WHERE CreditoID=4728 AND Activo=1 AND EsMora=0")->fetchColumn();
$tr4728 = (float) $pdo->query("SELECT COALESCE(SUM(sre.MontoAplicar),0) FROM solicitudes_resolucion_excedente sre JOIN pago p2 ON sre.PagoOrigenID=p2.PagoID WHERE p2.CreditoID=4728 AND sre.TipoResolucion='TRASLADO_DE_PAGO' AND sre.Estado='APROBADA'")->fetchColumn();
$saldo4728 = max(0, round(157.50 - ($tp4728 - $tr4728), 2));
$pdo->prepare("UPDATE ProposicionCredito SET SaldoPendiente = ? WHERE ProposicionCreditoID = 4728")->execute([$saldo4728]);
echo "C-004728: Pagado=S/ " . number_format($tp4728 - $tr4728, 2) . " | Saldo=S/ " . number_format($saldo4728, 2) . " | " . ($saldo4728 <= 0 ? "SALDADO" : "ACTIVO") . "\n";

// 4. Recalcular C-006306
$tp6308 = (float) $pdo->query("SELECT COALESCE(SUM(MontoPagado),0) FROM pago WHERE CreditoID=6308 AND Activo=1 AND EsMora=0")->fetchColumn();
$tr6308 = (float) $pdo->query("SELECT COALESCE(SUM(sre.MontoAplicar),0) FROM solicitudes_resolucion_excedente sre JOIN pago p2 ON sre.PagoOrigenID=p2.PagoID WHERE p2.CreditoID=6308 AND sre.TipoResolucion='TRASLADO_DE_PAGO' AND sre.Estado='APROBADA'")->fetchColumn();
$saldo6308 = max(0, round(105.00 - ($tp6308 - $tr6308), 2));
$pdo->prepare("UPDATE ProposicionCredito SET SaldoPendiente = ? WHERE ProposicionCreditoID = 6320")->execute([$saldo6308]);
echo "C-006306: Pagado=S/ " . number_format($tp6308 - $tr6308, 2) . " | Saldo=S/ " . number_format($saldo6308, 2) . " | " . ($saldo6308 <= 0 ? "SALDADO" : "ACTIVO") . "\n";

echo "\n========================================\n";
echo "  LISTO\n";
echo "========================================\n";
