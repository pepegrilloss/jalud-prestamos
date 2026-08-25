<?php
/**
 * REPARAR CREDITO C-000504 (RAMOS SAENZ JOSE DEL CARMEN)
 *
 * El credito fue marcado como SALDADO / SaldoPendiente=0 por la accion "Saldar"
 * con un pago parcial (S/5,880 de S/9,315), dejando un saldo real de S/3,435.
 *
 * Este script:
 *   1. Recalcula el saldo real con SaldoPendienteService::recalcular
 *      (pone SaldoPendiente=3435 y revierte SALDADO -> ACTIVO).
 *   2. Revierte a NORMAL las cuotas marcadas PAGADA que no estan realmente
 *      cubiertas por pagos (total pagado en la cuota < MontoCuota).
 *
 * Seguro e idempotente: si el credito ya esta correcto, no hace nada.
 *
 * Uso (en PRODUCCION):
 *   Subir este archivo a la raiz del proyecto y ejecutar:
 *     php corregir_credito_C-000504.php
 *   Despues de confirmar el resultado, borrar el archivo.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\SaldoPendienteService;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  REPARAR CREDITO C-000504\n";
echo "============================================================\n\n";

$pc = DB::table('ProposicionCredito')->where('CodigoCredito', 'C-000504')->first();

if (! $pc) {
    echo "No existe la proposicion C-000504.\n";
    exit(1);
}

$credito = DB::table('Credito')
    ->where('ProposicionCreditoID', $pc->ProposicionCreditoID)
    ->where('Activo', 1)
    ->first();

if (! $credito) {
    echo "No existe credito activo para C-000504.\n";
    exit(1);
}

$totalPagado = (float) DB::table('pago')
    ->where('CreditoID', $credito->CreditoID)
    ->where('Activo', 1)
    ->where('EsMora', 0)
    ->sum('MontoPagado');

$montoTotalPagar = (float) ($pc->MontoTotalPagar ?? 0);
$saldoReal = max(0, $montoTotalPagar - $totalPagado);

echo "CreditoID: {$credito->CreditoID}\n";
echo "MontoTotalPagar: S/ " . number_format($montoTotalPagar, 2) . "\n";
echo "Total pagado (activos, sin mora): S/ " . number_format($totalPagado, 2) . "\n";
echo "SALDO REAL: S/ " . number_format($saldoReal, 2) . "\n";
echo "Estado actual -> SaldoPendiente: {$pc->SaldoPendiente} | Estatus: {$credito->EstatusCreditoFinal} | FechaSaldamiento: " . ($credito->FechaSaldamiento ?? '-') . "\n\n";

// Si el saldo real es 0, el credito esta correctamente saldado -> nada que hacer
if ($saldoReal <= 0) {
    echo "El credito ya esta correctamente saldado. No se hizo ningun cambio.\n";
    exit(0);
}

// Solo intervenir si el credito esta mal marcado (saldo 0 y/o SALDADO)
$estaMal = ((float) $pc->SaldoPendiente) <= 0 || $credito->EstatusCreditoFinal === 'SALDADO';

if (! $estaMal) {
    echo "El credito ya tiene saldo correcto. No se hizo ningun cambio.\n";
    exit(0);
}

echo "Se detecto el credito mal marcado. Aplicando correccion...\n";

$pdo->beginTransaction();
try {
    // 1. Recalcular saldo (pone SaldoPendiente = saldo real y revierte SALDADO -> ACTIVO)
    $nuevoSaldo = SaldoPendienteService::recalcular((int) $pc->ProposicionCreditoID);

    // 2. Revertir cuotas marcadas PAGADA que no estan cubiertas por pagos
    $cuotas = DB::table('cuota')
        ->where('CreditoID', $credito->CreditoID)
        ->where('Activo', 1)
        ->where('Estado', 'PAGADA')
        ->get();

    $revertidas = 0;
    foreach ($cuotas as $cuota) {
        $pagadoEnCuota = (float) DB::table('pago')
            ->where('CreditoID', $credito->CreditoID)
            ->where('CuotaID', $cuota->CuotaID)
            ->where('Activo', 1)
            ->where('EsMora', 0)
            ->sum('MontoPagado');

        if ($pagadoEnCuota < (float) $cuota->MontoCuota) {
            DB::table('cuota')
                ->where('CuotaID', $cuota->CuotaID)
                ->update([
                    'Estado' => 'NORMAL',
                    'FechaPago' => null,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                ]);
            $revertidas++;
            echo "  Cuota #{$cuota->NumeroCuota} revertida a NORMAL (pagado S/ " . number_format($pagadoEnCuota, 2) . " de S/ " . number_format($cuota->MontoCuota, 2) . ")\n";
        }
    }

    $pdo->commit();

    $credito2 = DB::table('Credito')->where('CreditoID', $credito->CreditoID)->first();
    $pc2 = DB::table('ProposicionCredito')->where('ProposicionCreditoID', $pc->ProposicionCreditoID)->first();

    echo "\n============================================================\n";
    echo "  RESULTADO\n";
    echo "  SaldoPendiente: {$pc2->SaldoPendiente}\n";
    echo "  EstatusCreditoFinal: {$credito2->EstatusCreditoFinal}\n";
    echo "  FechaSaldamiento: " . ($credito2->FechaSaldamiento ?? 'NULL') . "\n";
    echo "  Cuotas revertidas a NORMAL: {$revertidas}\n";
    echo "============================================================\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
