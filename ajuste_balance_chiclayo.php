<?php
/**
 * AJUSTE DE BALANCE CHICLAYO - REMESA POR MIGRACION SICOPA
 *
 * Registra una remesa de S/ 12,285.59 desde Gerencia (Sede 3) hacia
 * Chiclayo (Sede 1) con fecha retroactiva al 27-jun-2026, para que el
 * Saldo Inicial del 30-jun quede en 15,469.60 (como cuadra con la señora).
 *
 * Mismo patrón que la remesa ID=87 (S/ 561,128.86) que ya se usó para cuadrar.
 *
 * Ejecutar UNA SOLA VEZ: php ajuste_balance_chiclayo.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$monto = 12285.59;
$fecha = '2026-06-27 16:15:00';

echo "============================================================\n";
echo "  AJUSTE BALANCE CHICLAYO (remesa migracion SICOPA)\n";
echo "  Monto: S/ " . number_format($monto, 2) . "\n";
echo "  Fecha: $fecha\n";
echo "============================================================\n\n";

// ─── Verificar que no exista ya un ajuste similar ───
$yaExiste = (float) $pdo->query("SELECT COALESCE(SUM(Monto),0) FROM transferencia_sedes WHERE Observacion LIKE '%AJUSTE%MIGRACION%CUADRAR BALANCE%' AND SedeDestinoID=1")->fetchColumn();
if ($yaExiste >= $monto) {
    echo "[ABORTADO] Ya existe un ajuste de migracion para Chiclayo por S/ " . number_format($yaExiste, 2) . ". NO se ejecuto nada.\n";
    exit(1);
}

echo "¿Continuar? Presiona ENTER o CTRL+C para cancelar...";
fgets(STDIN);

$pdo->beginTransaction();
try {
    // 1. Crear la transferencia (Sede 3 Gerencia -> Sede 1 Chiclayo, CAJA_ABIERTA -> CAJA_ABIERTA)
    $pdo->prepare("
        INSERT INTO transferencia_sedes
        (SedeOrigenID, SedeDestinoID, CuentaOrigen, CuentaDestino,
         EsSolicitudCapital, EsSolicitudGerencia, UsuarioOrigenID, UsuarioRespondeID,
         Monto, MontoAprobado, Estado, Observacion,
         FechaTransferencia, FechaRespuesta, FechaCierre, created_at, updated_at)
        VALUES (3, 1, 'CAJA_ABIERTA', 'CAJA_ABIERTA',
                0, 0, 13, 13,
                ?, NULL, 'ACEPTADO', 'AJUSTE POR MIGRACION SICOPA PARA CUADRAR BALANCE CHICLAYO',
                ?, ?, NULL, NOW(), NOW())
    ")->execute([$monto, $fecha, $fecha]);

    $transferenciaId = (int) $pdo->lastInsertId();
    echo "[OK] Transferencia creada: ID=$transferenciaId (S/ " . number_format($monto, 2) . ")\n";

    // 2. Registrar movimiento de fondo (RECEPCION_TRANSFERENCIA)
    $saldoAnterior = (float) $pdo->query("SELECT Saldo FROM fondo_sedes WHERE SedeID=1")->fetchColumn();
    $saldoNuevo = $saldoAnterior + $monto;

    $pdo->prepare("
        INSERT INTO movimientos_fondo
        (SedeID, Tipo, Monto, SaldoAnterior, SaldoNuevo, TransferenciaID,
         UsuarioID, Observacion, FechaMovimiento, created_at, updated_at)
        VALUES (1, 'RECEPCION_TRANSFERENCIA', ?, ?, ?, ?,
                13, 'AJUSTE POR MIGRACION SICOPA PARA CUADRAR BALANCE CHICLAYO', ?, NOW(), NOW())
    ")->execute([$monto, $saldoAnterior, $saldoNuevo, $transferenciaId, $fecha]);

    echo "[OK] MovimientoFondo registrado: Saldo S/ " . number_format($saldoAnterior, 2) . " -> S/ " . number_format($saldoNuevo, 2) . "\n";

    // 3. Actualizar fondo_sedes
    $pdo->prepare("UPDATE fondo_sedes SET Saldo = ?, updated_at = NOW() WHERE SedeID = 1")->execute([$saldoNuevo]);
    echo "[OK] FondoSede actualizado a S/ " . number_format($saldoNuevo, 2) . "\n";

    // 4. Registrar en logs
    $pdo->prepare("
        INSERT INTO logs (user_id, accion, modelo, modelo_id, old_values, new_values, created_at, SedeID)
        VALUES (13, 'AJUSTE_MIGRACION', 'TransferenciaSede', ?, NULL, ?, NOW(), 1)
    ")->execute([
        $transferenciaId,
        json_encode([
            'SedeOrigenID' => 3,
            'SedeDestinoID' => 1,
            'Monto' => $monto,
            'Estado' => 'ACEPTADO',
            'FechaRespuesta' => $fecha,
            'Motivo' => 'Cuadrar balance Chiclayo 30-jun (migracion SICOPA)',
        ]),
    ]);
    echo "[OK] Log registrado\n";

    $pdo->commit();
    echo "\n[OK] Ajuste aplicado correctamente.\n";

} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Transaccion revertida. No se aplico ningun cambio.\n";
    exit(1);
}

// ─── 5. Verificacion: Saldo Inicial 30-jun ───
echo "\n=== VERIFICACION SALDO INICIAL 30-JUN (Sede 1) ===\n";
$fechaLimite = "2026-06-30 00:00:00";

$tr = (float)$pdo->query("SELECT COALESCE(SUM(Monto),0) FROM transferencia_sedes WHERE SedeDestinoID=1 AND Estado='ACEPTADO' AND CuentaDestino='CAJA_ABIERTA' AND ((FechaRespuesta IS NOT NULL AND FechaRespuesta<'$fechaLimite') OR (FechaRespuesta IS NULL AND FechaTransferencia<'$fechaLimite'))")->fetchColumn();
$te = (float)$pdo->query("SELECT COALESCE(SUM(Monto),0) FROM transferencia_sedes WHERE SedeOrigenID=1 AND Estado='ACEPTADO' AND CuentaOrigen='CAJA_ABIERTA' AND ((FechaRespuesta IS NOT NULL AND FechaRespuesta<'$fechaLimite') OR (FechaRespuesta IS NULL AND FechaTransferencia<'$fechaLimite'))")->fetchColumn();
$pagos = (float)$pdo->query("SELECT COALESCE(SUM(MontoPagado),0) FROM pago WHERE SedeID=1 AND Activo=1 AND FechaPago<'$fechaLimite' AND EsPagoAMayorPorMora=0 AND (TipoConcepto IS NULL OR TipoConcepto='C') AND (EsPagoAMayor=0 OR SolicitudResolucionID IS NULL)")->fetchColumn();
$cred = (float)$pdo->query("SELECT COALESCE(SUM(pc.MontoTotal),0) FROM Credito c JOIN ProposicionCredito pc ON pc.ProposicionCreditoID=c.ProposicionCreditoID WHERE c.SedeID=1 AND c.Activo=1 AND c.FechaGeneracion<'$fechaLimite'")->fetchColumn();
$exc = (float)$pdo->query("SELECT COALESCE(SUM(e.Monto+COALESCE((SELECT SUM(sre.MontoAplicar) FROM solicitudes_resolucion_excedente sre WHERE sre.ExcedenteID=e.ExcedenteID AND sre.Estado='APROBADA'),0)),0) FROM excedentes e WHERE e.SedeID=1 AND e.Activo=1 AND (e.Cuenta IS NULL OR e.Cuenta='Caja Abierta') AND e.Fecha<'$fechaLimite'")->fetchColumn();
$mor = (float)$pdo->query("SELECT COALESCE(SUM(MontoPagado),0) FROM pago WHERE SedeID=1 AND Activo=1 AND EsPagoAMayorPorMora=1 AND FechaPago<'$fechaLimite'")->fetchColumn();

$raw = $tr - $te + $pagos - $cred + $exc + $mor;
$pdf = $raw - 150000;

echo "  Transf Recibidas: +" . number_format($tr, 2) . "\n";
echo "  Transf Enviadas:  -" . number_format($te, 2) . "\n";
echo "  Pagos:            +" . number_format($pagos, 2) . "\n";
echo "  Creditos:         -" . number_format($cred, 2) . "\n";
echo "  Excedentes:       +" . number_format($exc, 2) . "\n";
echo "  Moras:            +" . number_format($mor, 2) . "\n";
echo "  -----------------------------------------\n";
echo "  RAW:              " . number_format($raw, 2) . "\n";
echo "  PDF (-150K):      " . number_format($pdf, 2) . "  (esperado: 15,469.60)\n";

// TOTAL del dia
$total = $pdf + 37780.67 + 3.00 - 20125.00 - 7126.87;
echo "  TOTAL 30-jun:     " . number_format($total, 2) . "  (esperado: 26,001.40)\n";

echo "\nListo.\n";
