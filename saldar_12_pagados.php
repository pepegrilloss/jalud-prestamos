<?php
/**
 * Marca como SALDADO 12 creditos que ya estan 100% pagados pero siguen ACTIVO.
 * Ejecutar UNA SOLA VEZ: php saldar_12_pagados.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$codigos = [
    'C-001218', 'C-001593', 'C-004812', 'C-005056',
    'C-005145', 'C-005528', 'C-005657', 'C-005665',
    'C-005784', 'C-005810', 'C-005924', 'C-006065',
];

echo "========================================\n";
echo "  SALDAR 12 CREDITOS YA PAGADOS\n";
echo "========================================\n\n";

$saldados = 0;

foreach ($codigos as $codigo) {
    $stmt = $pdo->prepare("
        SELECT pc.ProposicionCreditoID, pc.MontoTotalPagar, pc.SaldoPendiente,
               c.CreditoID, c.EstatusCreditoFinal, cl.NombresApellidos
        FROM ProposicionCredito pc
        JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
        JOIN Cliente cl ON cl.ClienteID = pc.ClienteID
        WHERE pc.CodigoCredito = ?
    ");
    $stmt->execute([$codigo]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$r) {
        echo "  [SKIP] $codigo - No encontrado\n";
        continue;
    }

    if ($r['EstatusCreditoFinal'] === 'SALDADO') {
        echo "  [SKIP] $codigo - Ya esta SALDADO\n";
        continue;
    }

    $creditoID = (int) $r['CreditoID'];
    $propID = (int) $r['ProposicionCreditoID'];

    $tp = (float) $pdo->query("SELECT COALESCE(SUM(MontoPagado),0) FROM pago WHERE CreditoID=$creditoID AND Activo=1 AND EsMora=0")->fetchColumn();
    $tr = (float) $pdo->query("SELECT COALESCE(SUM(sre.MontoAplicar),0) FROM solicitudes_resolucion_excedente sre JOIN pago p2 ON sre.PagoOrigenID=p2.PagoID WHERE p2.CreditoID=$creditoID AND sre.TipoResolucion='TRASLADO_DE_PAGO' AND sre.Estado='APROBADA'")->fetchColumn();
    $pagado = round($tp - $tr, 2);
    $mfp = (float) $r['MontoTotalPagar'];

    if ($pagado < $mfp) {
        echo "  [SKIP] $codigo - {$r['NombresApellidos']} - NO esta totalmente pagado (Pagado=S/ $pagado, MFP=S/ $mfp)\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE Credito SET EstatusCreditoFinal='SALDADO', FechaSaldamiento=NOW() WHERE CreditoID=?")->execute([$creditoID]);
        $pdo->prepare("UPDATE cuota SET Estado='PAGADA', FechaPago=NOW() WHERE CreditoID=? AND Estado IN('PENDIENTE','NORMAL','MORA','VENCIDA')")->execute([$creditoID]);
        $pdo->prepare("INSERT INTO logs (user_id, accion, modelo, modelo_id, old_values, new_values, created_at, SedeID) VALUES (0,'SALDAR_PAGADOS','Credito',?,?,?,NOW(),(SELECT SedeID FROM Credito WHERE CreditoID=?))")->execute([
            $creditoID,
            json_encode(['EstatusCreditoFinal' => 'ACTIVO']),
            json_encode(['EstatusCreditoFinal' => 'SALDADO']),
            $creditoID,
        ]);
        $pdo->commit();
        $saldados++;
        echo "  [OK] $codigo - {$r['NombresApellidos']} - MFP=S/ $mfp - Saldado\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  [ERROR] $codigo - {$e->getMessage()}\n";
    }
}

echo "\n========================================\n";
echo "  RESULTADO: $saldados saldado(s)\n";
echo "========================================\n";
