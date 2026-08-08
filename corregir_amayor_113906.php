<?php
/**
 * CORRIGE en PRD: extorno #21 del C-003603 vuelve a PAGO A MAYOR.
 *
 * Confirmado por la senora: el UNICO pago de extorno que debe marcarse como
 * pago a mayor es el PagoID=113906 (Solicitud #21, ASIGNACION_POR_RECLAMO,
 * S/10.00) del credito C-003603. El credito estaba SALDADO al momento del
 * extorno (saldo 0), por lo que el pago generado es a mayor.
 *
 * El pago nacio con EsPagoAMayor=1 (log CREAR), pero el script
 * corregir_extornos_amayor.php (que deja TODOS los extornos en 0) lo
 * habria bajado a 0. Este script lo restaura a 1.
 *
 * IDEMPOTENTE: si ya esta en 1, no hace nada.
 *
 * Ejecutar UNA SOLA VEZ en PRD: php corregir_amayor_113906.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR PAGO 113906 (C-003603 extorno #21) -> A MAYOR\n";
echo "============================================================\n\n";

// ─── 1. Verificar que el pago existe y es el correcto ───
$pago = DB::table('pago as p')
    ->join('Credito as c', 'c.CreditoID', '=', 'p.CreditoID')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('p.PagoID', 113906)
    ->select('p.PagoID', 'p.MontoPagado', 'p.FechaPago', 'p.EsPagoAMayor', 'p.SolicitudResolucionID', 'pc.CodigoCredito')
    ->first();

if (!$pago) {
    echo "[ERROR] No existe el PagoID 113906 en esta base.\n";
    exit(1);
}

echo "Pago 113906 encontrado:\n";
echo "  Credito: {$pago->CodigoCredito}\n";
echo "  Monto: S/{$pago->MontoPagado} | Fecha: {$pago->FechaPago}\n";
echo "  EsPagoAMayor actual: {$pago->EsPagoAMayor} | SolicitudResolucionID: " . ($pago->SolicitudResolucionID ?? 'NULL') . "\n";

if ($pago->SolicitudResolucionID != 21) {
    echo "\n[ADVERTENCIA] La solicitud asociada no es la #21. Revise antes de continuar.\n";
}

if ((int) $pago->EsPagoAMayor === 1) {
    echo "\nYa esta marcado como A MAYOR (EsPagoAMayor=1). Nada que hacer.\n";
    exit(0);
}

echo "\nSe pondra EsPagoAMayor=1 en este pago.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 2. Aplicar ───
$pdo->beginTransaction();
try {
    DB::table('pago')->where('PagoID', 113906)->update(['EsPagoAMayor' => 1]);

    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_AMAYOR',
        'modelo' => 'Pago',
        'modelo_id' => 113906,
        'old_values' => json_encode(['EsPagoAMayor' => 0]),
        'new_values' => json_encode(['EsPagoAMayor' => 1, 'Motivo' => 'Extorno #21 C-003603: credito estaba saldado al momento del extorno (saldo 0) - UNICO pago a mayor confirmado por la senora']),
        'created_at' => now(),
        'SedeID' => 1,
    ]);

    $pdo->commit();
    echo "\nAPLICADO CORRECTAMENTE.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// ─── 3. Verificacion ───
$p2 = DB::table('pago')->where('PagoID', 113906)->first();
echo "\n=== VERIFICACION ===\n";
echo "  EsPagoAMayor={$p2->EsPagoAMayor} | SolicitudResolucionID={$p2->SolicitudResolucionID}\n";
$n = DB::table('pago')->whereNotNull('SolicitudResolucionID')->where('EsPagoAMayor', 1)->count();
echo "  Total pagos extorno con EsPagoAMayor=1: $n (debe ser 1)\n";
echo "  Log CORR_AMAYOR: " . DB::table('logs')->where('accion', 'CORR_AMAYOR')->count() . "\n";
echo "\n  NOTA: despues de correr este script, subir tambien la UI corregida\n";
echo "  (pagos-table.blade.php, PagoResource.php, ViewPago.php) para que el badge\n";
echo "  muestre PAGO A MAYOR aunque el pago tenga SolicitudResolucionID.\n";
echo "\n============================================================\n";
echo "  COMPLETADO\n";
echo "============================================================\n";
