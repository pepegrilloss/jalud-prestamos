<?php
/**
 * Reparar refinanciamientos rotos
 * Corrige pagos automaticos con monto 0 y saldos inconsistentes
 *
 * Ejecutar: php reparar_refinanciamientos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== REPARAR REFINANCIAMIENTOS ===\n\n";

// 1. Buscar TODOS los refinanciamientos con credito generado
$refis = DB::table('ProposicionCredito as pc')
    ->join('Credito as c', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.SedeID', 1)
    ->where('pc.EsRefinanciamiento', 1)
    ->whereNotNull('pc.ProposicionCreditoAnteriorID')
    ->select('pc.ProposicionCreditoID', 'pc.CodigoCredito', 'pc.ProposicionCreditoAnteriorID', 'pc.Estado', 'pc.MontoTotal', 'c.CreditoID', 'c.EstatusCreditoFinal')
    ->orderBy('pc.ProposicionCreditoID', 'desc')
    ->get();

echo "Refinanciamientos encontrados: " . count($refis) . "\n\n";

$reparados = 0;
$yaOk = 0;

foreach ($refis as $refi) {
    // Buscar proposicion anterior
    $ant = DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $refi->ProposicionCreditoAnteriorID)
        ->first(['ProposicionCreditoID', 'CodigoCredito', 'SaldoPendiente', 'FueRefinanciada', 'Activo']);

    if (!$ant) {
        echo "  [??] {$refi->CodigoCredito} -> Anterior ID={$refi->ProposicionCreditoAnteriorID} no encontrado\n";
        continue;
    }

    // Buscar credito anterior
    $credAnt = DB::table('Credito')
        ->where('ProposicionCreditoID', $ant->ProposicionCreditoID)
        ->first(['CreditoID', 'EstatusCreditoFinal']);

    // Buscar pago automatico existente
    $pagoAuto = DB::table('pago')
        ->where('CreditoID', $credAnt->CreditoID ?? 0)
        ->where('EsPagoAutomatico', 1)
        ->where('Activo', 1)
        ->first(['PagoID', 'MontoPagado']);

    $necesitaFix = false;

    // Verificar si necesita reparacion
    if (!$pagoAuto || (float)$pagoAuto->MontoPagado == 0) {
        $necesitaFix = true;
    }
    if ((float)$ant->SaldoPendiente != 0) {
        $necesitaFix = true;
    }
    if ($credAnt && $credAnt->EstatusCreditoFinal !== 'SALDADO') {
        $necesitaFix = true;
    }
    if (!$ant->FueRefinanciada) {
        $necesitaFix = true;
    }

    if (!$necesitaFix) {
        $yaOk++;
        continue;
    }

    $saldo = (float)$ant->SaldoPendiente;
    $montoPago = $saldo;

    // Si hay pago auto con monto 0, actualizarlo
    if ($pagoAuto && (float)$pagoAuto->MontoPagado == 0) {
        // Calcular monto correcto: total de otros pagos vs monto total
        $mtp = DB::table('ProposicionCredito')->where('ProposicionCreditoID', $ant->ProposicionCreditoID)->value('MontoTotalPagar');
        $otros = DB::table('pago')
            ->where('CreditoID', $credAnt->CreditoID)
            ->where('Activo', 1)
            ->where('PagoID', '!=', $pagoAuto->PagoID)
            ->sum('MontoPagado');
        $montoPago = max(0, (float)$mtp - (float)$otros);

        DB::table('pago')
            ->where('PagoID', $pagoAuto->PagoID)
            ->update(['MontoPagado' => $montoPago]);
    } else if (!$pagoAuto && $saldo > 0) {
        // No hay pago automatico, crear uno
        $cuotaRef = DB::table('cuota')->where('CreditoID', $credAnt->CreditoID)->orderBy('NumeroCuota')->first();
        $montoPago = $saldo;
        DB::table('pago')->insert([
            'CreditoID' => $credAnt->CreditoID,
            'CuotaID' => $cuotaRef->CuotaID ?? null,
            'MontoPagado' => $montoPago,
            'FechaPago' => now(),
            'SedeID' => 1,
            'EsMora' => 0,
            'EsPagoAMayor' => 0,
            'EsPagoForzado' => 0,
            'EsPagoAutomatico' => 1,
            'Comentario' => "Pago automatico por refinanciamiento - reparado",
            'UsuarioRegistro' => 'Sistema',
            'Activo' => 1,
        ]);
    }

    // Marcar proposicion anterior
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $ant->ProposicionCreditoID)
        ->update([
            'FueRefinanciada' => 1,
            'SaldoPendiente' => 0,
            'Activo' => 0,
        ]);

    // Marcar credito anterior como SALDADO
    if ($credAnt) {
        DB::table('Credito')
            ->where('CreditoID', $credAnt->CreditoID)
            ->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => now(),
            ]);
    }

    // Marcar cuotas pendientes como pagadas
    DB::table('cuota')
        ->where('CreditoID', $credAnt->CreditoID ?? 0)
        ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
        ->update([
            'Estado' => 'PAGADA',
            'FechaPago' => now(),
        ]);

    echo "  [OK] {$refi->CodigoCredito} (nuevo) → {$ant->CodigoCredito} (anterior) | Pago S/{$montoPago} | SALDADO\n";
    $reparados++;
}

echo "\n=== RESUMEN ===\n";
echo "  Reparados:  {$reparados}\n";
echo "  Ya OK:      {$yaOk}\n";
echo "\nDone.\n";
