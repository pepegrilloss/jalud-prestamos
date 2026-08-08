<?php
/**
 * CORRIGE EsPagoAMayor de pagos generados por Extornos/Resoluciones.
 *
 * Regla de negocio (confirmada): SOLO los pagos a mayor REALES (registrados
 * manualmente como MAYOR, sin SolicitudResolucionID) se marcan como
 * EsPagoAMayor=1. Los pagos generados por resoluciones (ASIGNACION_POR_RECLAMO,
 * TRASLADO_DE_PAGO, APLICACION_NUEVO_CREDITO, etc.) NO son pagos a mayor:
 * son asientos virtuales de regularización (etiqueta "Extorno/Regularización").
 *
 * SOLO actualiza pago.EsPagoAMayor = 0 donde SolicitudResolucionID IS NOT NULL.
 * NO toca: saldos (se descuentan igual), balances (ya excluidos por
 * SolicitudResolucionID en reportes), movimientos de caja, solicitudes.
 *
 * IDEMPOTENTE. Ejecutar: php corregir_extornos_amayor.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR EsPagoAMayor DE EXTORNOS (SolicitudResolucionID)\n";
echo "============================================================\n\n";

// ─── 1. Candidatos ───
$pagos = DB::table('pago as p')
    ->join('solicitudes_resolucion_excedente as sre', 'sre.SolicitudID', '=', 'p.SolicitudResolucionID')
    ->whereNotNull('p.SolicitudResolucionID')
    ->where('p.EsPagoAMayor', 1)
    ->select('p.PagoID', 'p.CreditoID', 'p.MontoPagado', 'p.FechaPago', 'p.SedeID', 'sre.TipoResolucion', 'sre.SolicitudID')
    ->orderBy('p.PagoID')
    ->get();

echo "Pagos con SolicitudResolucionID y EsPagoAMayor=1: " . count($pagos) . "\n";

$porTipo = [];
foreach ($pagos as $p) $porTipo[$p->TipoResolucion] = ($porTipo[$p->TipoResolucion] ?? 0) + 1;
foreach ($porTipo as $t => $n) echo "  $t: $n\n";

echo "\nMuestra (primeros 15):\n";
foreach (array_slice($pagos->all(), 0, 15) as $p) {
    echo "  PagoID={$p->PagoID} | S/{$p->MontoPagado} | {$p->FechaPago} | CreditoID={$p->CreditoID} | Sede={$p->SedeID} | Sol={$p->SolicitudID} | {$p->TipoResolucion}\n";
}

if (count($pagos) === 0) {
    echo "\nNada que corregir.\n";
    exit(0);
}

echo "\nSe pondra EsPagoAMayor=0 en " . count($pagos) . " pagos (asientos de resolucion).\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 2. Aplicar ───
$corregidos = 0;
$conError = 0;

foreach ($pagos as $p) {
    $pdo->beginTransaction();
    try {
        DB::table('pago')->where('PagoID', $p->PagoID)
            ->update(['EsPagoAMayor' => 0]);

        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_EXT',
            'modelo' => 'Pago',
            'modelo_id' => $p->PagoID,
            'old_values' => json_encode(['EsPagoAMayor' => 1]),
            'new_values' => json_encode(['EsPagoAMayor' => 0, 'Motivo' => 'Pago generado por resolucion (SolicitudResolucionID=' . $p->SolicitudID . ', tipo ' . $p->TipoResolucion . '): no es pago a mayor real']),
            'created_at' => now(),
            'SedeID' => $p->SedeID,
        ]);

        $pdo->commit();
        $corregidos++;
    } catch (\Exception $e) {
        $pdo->rollBack();
        $conError++;
        echo "  [ERROR] PagoID={$p->PagoID}: {$e->getMessage()}\n";
    }
}

echo "\n============================================================\n";
echo "  RESULTADO: $corregidos corregidos | $conError errores\n";
echo "============================================================\n";
