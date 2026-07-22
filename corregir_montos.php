<?php
/**
 * Corrige MontoTotalPagar y SaldoPendiente para 9 creditos especificos.
 * Solo toca los creditos listados abajo, no modifica nada mas.
 * 
 * Ejecutar: php corregir_montos.php
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

$codigos = [
    'C-005957', 'C-006057', 'C-006095', 'C-006106',
    'C-006203', 'C-006249', 'C-006314', 'C-006351',
    'C-006486',
];

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  CORRECCION DE MONTOS (9 creditos)\n";
echo "  MontoTotalPagar = MontoTotal + MontoInteres\n";
echo "  SaldoPendiente = MAX(0, MontoTotalPagar - TotalPagado)\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$corregidos = 0;
$saldados = 0;

foreach ($codigos as $codigo) {
    // Obtener datos del credito
    $stmt = $pdo->prepare("
        SELECT pc.ProposicionCreditoID, pc.CodigoCredito, pc.MontoTotal, pc.MontoInteres,
               pc.MontoTotalPagar, pc.SaldoPendiente, c.CreditoID, c.EstatusCreditoFinal
        FROM ProposicionCredito pc
        JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID
        WHERE pc.CodigoCredito = ?
    ");
    $stmt->execute([$codigo]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cred) {
        echo "  [SKIP] $codigo - No encontrado\n";
        continue;
    }

    $creditoID = (int) $cred['CreditoID'];
    $propID = (int) $cred['ProposicionCreditoID'];
    $capital = (float) $cred['MontoTotal'];
    $interes = (float) $cred['MontoInteres'];
    $montoFinalAntes = (float) $cred['MontoTotalPagar'];
    $saldoAntes = (float) $cred['SaldoPendiente'];
    $estatusAntes = $cred['EstatusCreditoFinal'];

    // Calcular el verdadero Monto Total a Pagar
    $montoFinalNuevo = round($capital + $interes, 2);

    // Obtener total pagado (activos, no mora, descontando traslados)
    $stmtTp = $pdo->prepare("
        SELECT COALESCE(SUM(p.MontoPagado), 0)
        FROM pago p
        WHERE p.CreditoID = ? AND p.Activo = 1 AND p.EsMora = 0
    ");
    $stmtTp->execute([$creditoID]);
    $totalPagado = round((float) $stmtTp->fetchColumn(), 2);

    // Descontar traslados de pago aprobados
    $stmtTr = $pdo->prepare("
        SELECT COALESCE(SUM(sre.MontoAplicar), 0)
        FROM solicitudes_resolucion_excedente sre
        JOIN pago p2 ON sre.PagoOrigenID = p2.PagoID
        WHERE p2.CreditoID = ?
          AND sre.TipoResolucion = 'TRASLADO_DE_PAGO'
          AND sre.Estado = 'APROBADA'
    ");
    $stmtTr->execute([$creditoID]);
    $traslados = round((float) $stmtTr->fetchColumn(), 2);

    $totalPagadoNeto = $totalPagado - $traslados;
    $saldoNuevo = max(0, round($montoFinalNuevo - $totalPagadoNeto, 2));

    // Verificar si realmente necesita correccion
    if ($montoFinalAntes == $montoFinalNuevo && $saldoAntes == $saldoNuevo) {
        echo "  $codigo: Ya esta correcto, se omite.\n";
        continue;
    }

    echo "  $codigo (CreditoID=$creditoID):\n";
    echo "    MontoFinal: S/ " . number_format($montoFinalAntes, 2) . " -> S/ " . number_format($montoFinalNuevo, 2) . "\n";
    echo "    Saldo:      S/ " . number_format($saldoAntes, 2) . " -> S/ " . number_format($saldoNuevo, 2) . "\n";

    // Determinar nuevo estatus
    $nuevoEstatus = $estatusAntes;
    if ($saldoNuevo <= 0 && $totalPagadoNeto > 0 && $estatusAntes !== 'SALDADO') {
        $nuevoEstatus = 'SALDADO';
        echo "    Estatus:    {$estatusAntes} -> SALDADO\n";
        $saldados++;
    }

    // Aplicar correccion
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE ProposicionCredito
            SET MontoTotalPagar = ?, SaldoPendiente = ?
            WHERE ProposicionCreditoID = ?
        ")->execute([$montoFinalNuevo, $saldoNuevo, $propID]);

        if ($nuevoEstatus !== $estatusAntes) {
            $pdo->prepare("
                UPDATE Credito
                SET EstatusCreditoFinal = 'SALDADO', FechaSaldamiento = NOW()
                WHERE CreditoID = ?
            ")->execute([$creditoID]);

            $pdo->prepare("
                UPDATE cuota
                SET Estado = 'PAGADA', FechaPago = NOW()
                WHERE CreditoID = ? AND Estado IN ('PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA')
            ")->execute([$creditoID]);
        }

        // Registrar en logs
        $pdo->prepare("
            INSERT INTO logs (user_id, accion, modelo, modelo_id, old_values, new_values, created_at, SedeID)
            VALUES (0, 'CORREGIR_MONTOS', 'Credito', ?,
                ?, ?,
                NOW(),
                (SELECT SedeID FROM Credito WHERE CreditoID = ?)
            )
        ")->execute([
            $creditoID,
            json_encode(['MontoTotalPagar' => $montoFinalAntes, 'SaldoPendiente' => $saldoAntes, 'EstatusCreditoFinal' => $estatusAntes]),
            json_encode(['MontoTotalPagar' => $montoFinalNuevo, 'SaldoPendiente' => $saldoNuevo, 'EstatusCreditoFinal' => $nuevoEstatus]),
            $creditoID,
        ]);

        $pdo->commit();
        $corregidos++;
        echo "    [OK] Corregido.\n\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "    [ERROR] {$e->getMessage()}\n\n";
    }
}

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  RESULTADO: $corregidos corregido(s), $saldados marcado(s) como SALDADO\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
