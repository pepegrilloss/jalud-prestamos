<?php
/**
 * UNIFICACION DE CLIENTE DUPLICADO
 * Cliente correcto:  ClienteID=515  (DNI 71335268)
 * Cliente duplicado: ClienteID=1442 (DNI 71335269)
 *
 * Mueve todos los registros del duplicado (1442) al correcto (515)
 * y desactiva el duplicado. SOLO toca estos 2 clientes.
 *
 * Ejecutar UNA SOLA VEZ: php unificar_cliente_71335268.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$clienteCorrecto = 515;   // DNI 71335268 (DELGADO CRUZ HISELA)
$clienteDuplicado = 1442; // DNI 71335269 (DELGADO CRUZ HISELA)

echo "============================================================\n";
echo "  UNIFICACION CLIENTE DUPLICADO (71335268)\n";
echo "  Correcto: ClienteID=$clienteCorrecto (DNI 71335268)\n";
echo "  Duplicado: ClienteID=$clienteDuplicado (DNI 71335269)\n";
echo "============================================================\n\n";

// Verificar que ambos clientes existen
$stmt = $pdo->prepare("SELECT ClienteID, NombresApellidos, DNI, Activo FROM Cliente WHERE ClienteID IN (?, ?)");
$stmt->execute([$clienteCorrecto, $clienteDuplicado]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($clientes) !== 2) {
    die("ERROR: No se encontraron ambos clientes. Abortando.\n");
}
foreach ($clientes as $c) {
    echo "  ClienteID={$c['ClienteID']} | {$c['NombresApellidos']} | DNI={$c['DNI']} | Activo={$c['Activo']}\n";
}

// Verificar que el duplicado no tenga creditos ACTIVOS con saldo
$stmt = $pdo->prepare("
    SELECT pc.CodigoCredito, pc.SaldoPendiente, c.EstatusCreditoFinal
    FROM ProposicionCredito pc
    JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
    WHERE pc.ClienteID = ? AND pc.Activo = 1
");
$stmt->execute([$clienteDuplicado]);
$creditosDuplicado = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n  Creditos del duplicado:\n";
foreach ($creditosDuplicado as $cd) {
    $estado = ($cd['SaldoPendiente'] > 0 && $cd['EstatusCreditoFinal'] === 'ACTIVO') ? '⚠ TIENE SALDO' : 'OK (saldado/0)';
    echo "    {$cd['CodigoCredito']} | Saldo={$cd['SaldoPendiente']} | {$cd['EstatusCreditoFinal']} | $estado\n";
}

echo "\n  ¿Continuar con la unificacion? Presiona ENTER o CTRL+C para cancelar...";
fgets(STDIN);

$pdo->beginTransaction();
try {
    // 1. ProposicionCredito -> mover al cliente correcto
    $n = $pdo->prepare("UPDATE ProposicionCredito SET ClienteID = ?, CodigoCliente = '71335268' WHERE ClienteID = ?")->execute([$clienteCorrecto, $clienteDuplicado]);
    echo "[OK] ProposicionCredito: $n proposiciones movidas\n";

    // 2. Negocio -> desactivar el del duplicado (el correcto ya tiene negocio)
    $negocioDup = $pdo->prepare("SELECT NegocioID FROM Negocio WHERE ClienteID = ?");
    $negocioDup->execute([$clienteDuplicado]);
    $negID = $negocioDup->fetchColumn();
    if ($negID) {
        $pdo->prepare("UPDATE Negocio SET Activo = 0 WHERE NegocioID = ?")->execute([$negID]);
        echo "[OK] Negocio del duplicado (NegocioID=$negID) desactivado\n";
    } else {
        echo "[SKIP] Sin negocio duplicado\n";
    }

    // 3. AnalisisEconomico -> mover
    $n = $pdo->prepare("UPDATE AnalisisEconomico SET ClienteID = ? WHERE ClienteID = ?")->execute([$clienteCorrecto, $clienteDuplicado]);
    echo "[OK] AnalisisEconomico: $n registros movidos\n";

    // 4. EvaluacionCredito -> mover
    $n = $pdo->prepare("UPDATE EvaluacionCredito SET ClienteID = ? WHERE ClienteID = ?")->execute([$clienteCorrecto, $clienteDuplicado]);
    echo "[OK] EvaluacionCredito: $n registros movidos\n";

    // 5. solicitudes_resolucion_excedente -> mover ClienteDestinoID
    $n = $pdo->prepare("UPDATE solicitudes_resolucion_excedente SET ClienteDestinoID = ? WHERE ClienteDestinoID = ?")->execute([$clienteCorrecto, $clienteDuplicado]);
    echo "[OK] SolicitudesResolucion (destino): $n movidas\n";
    $n = $pdo->prepare("UPDATE solicitudes_resolucion_excedente SET ClienteOrigenID = ? WHERE ClienteOrigenID = ?")->execute([$clienteCorrecto, $clienteDuplicado]);
    echo "[OK] SolicitudesResolucion (origen): $n movidas\n";

    // 6. Desactivar cliente duplicado
    $pdo->prepare("UPDATE Cliente SET Activo = 0, Observaciones = CONCAT(COALESCE(Observaciones,''), ' [UNIFICADO con ClienteID=515]') WHERE ClienteID = ?")->execute([$clienteDuplicado]);
    echo "[OK] Cliente duplicado (ClienteID=$clienteDuplicado) desactivado\n";

    // Registrar en logs
    $pdo->prepare("INSERT INTO logs (user_id, accion, modelo, modelo_id, old_values, new_values, created_at, SedeID) VALUES (0,'UNIFICAR_CLIENTE','Cliente',?,?,?,NOW(),1)")->execute([
        $clienteDuplicado,
        json_encode(['ClienteID' => $clienteDuplicado, 'DNI' => '71335269', 'Accion' => 'Movido a ClienteID=515']),
        json_encode(['ClienteID' => $clienteCorrecto, 'DNI' => '71335268', 'Registros' => 'Proposiciones, Negocio, Analisis, Evaluaciones, Solicitudes']),
    ]);

    $pdo->commit();
    echo "\n============================================================\n";
    echo "  UNIFICACION COMPLETADA\n";
    echo "============================================================\n";

} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// Verificacion final
echo "\n=== VERIFICACION ===\n";
$stmt = $pdo->prepare("SELECT ClienteID, COUNT(*) as total FROM ProposicionCredito WHERE ClienteID IN (?, ?) GROUP BY ClienteID");
$stmt->execute([$clienteCorrecto, $clienteDuplicado]);
foreach ($stmt as $r) echo "  ClienteID={$r['ClienteID']}: {$r['total']} proposiciones\n";

$stmt = $pdo->prepare("SELECT ClienteID, DNI, Activo FROM Cliente WHERE ClienteID IN (?, ?)");
$stmt->execute([$clienteCorrecto, $clienteDuplicado]);
foreach ($stmt as $r) echo "  ClienteID={$r['ClienteID']} | DNI={$r['DNI']} | Activo={$r['Activo']}\n";
