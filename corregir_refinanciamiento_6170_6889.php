<?php
/**
 * CORRIGE el refinanciamiento de VASQUEZ ZUNIGA JORGE ANGEL.
 *
 * Situacion (confusion confirmada):
 *   - C-006170 (Prop 6184) debia ser refinanciado
 *   - C-006889 (Prop 6914) se creo como credito NORMAL en vez de REFINANCIAMIENTO
 *   - El monto del C-006889 (S/1,126.62) = saldo del C-006170 (evidencia)
 *   - El EGRESO_COLOCACION del C-006889 ya se registro (correcto)
 *
 * Correccion (replica la logica de GenerarCreditoResource::crearPagoAutomaticoRefinanciamiento):
 *   1. Proposicion 6914: EsRefinanciamiento=1, ProposicionCreditoAnteriorID=6184
 *   2. Crear pago automatico S/1,126.62 en el credito ANTERIOR (C-006170, CreditoID=6174)
 *   3. Cuotas pendientes del C-006170 -> PAGADO con fecha del refinanciamiento
 *   4. Proposicion 6184: FueRefinanciada=1, SaldoPendiente=0
 *   5. Credito C-006170: EstatusCreditoFinal=SALDADO, FechaSaldamiento
 *   6. Recalcular saldo del C-006170 (via servicio, para consistencia)
 *
 * Ejecutar UNA SOLA VEZ: php corregir_refinanciamiento_6170_6889.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR REFINANCIAMIENTO C-006170 -> C-006889\n";
echo "============================================================\n\n";

// ─── 1. Verificar estado actual ───
$nuevo = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.CodigoCredito', 'C-006889')
    ->select('c.CreditoID', 'pc.ProposicionCreditoID', 'pc.MontoTotal', 'pc.SaldoPendiente', 'pc.EsRefinanciamiento', 'pc.ProposicionCreditoAnteriorID', 'c.FechaGeneracion', 'c.SedeID')
    ->first();

$anterior = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.CodigoCredito', 'C-006170')
    ->select('c.CreditoID', 'pc.ProposicionCreditoID', 'pc.SaldoPendiente', 'pc.MontoTotalPagar', 'c.EstatusCreditoFinal', 'c.SedeID')
    ->first();

echo "C-006889 (nuevo): CreditoID={$nuevo->CreditoID} | Prop={$nuevo->ProposicionCreditoID} | Monto={$nuevo->MontoTotal} | EsRefin={$nuevo->EsRefinanciamiento} | PropAnterior=" . ($nuevo->ProposicionCreditoAnteriorID ?? 'NULL') . "\n";
echo "C-006170 (anterior): CreditoID={$anterior->CreditoID} | Prop={$anterior->ProposicionCreditoID} | Saldo={$anterior->SaldoPendiente} | Estatus={$anterior->EstatusCreditoFinal}\n\n";

// Verificaciones de seguridad
if ((int) $nuevo->EsRefinanciamiento === 1) {
    echo "[ERROR] El C-006889 ya esta marcado como refinanciamiento. Abortando.\n";
    exit(1);
}
if (abs((float) $nuevo->MontoTotal - (float) $anterior->SaldoPendiente) > 0.01) {
    echo "[ERROR] El monto del nuevo ({$nuevo->MontoTotal}) no coincide con el saldo del anterior ({$anterior->SaldoPendiente}). Revise manualmente.\n";
    exit(1);
}

$cuotasPend = DB::table('cuota')->where('CreditoID', $anterior->CreditoID)
    ->where('Activo', 1)->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA', 'NORMAL'])
    ->count();
echo "Cuotas pendientes del C-006170 a marcar PAGADO: {$cuotasPend}\n";
echo "Fecha del refinanciamiento: {$nuevo->FechaGeneracion}\n";

$yaPagoAuto = DB::table('pago')->where('CreditoID', $anterior->CreditoID)->where('EsPagoAutomatico', 1)->count();
if ($yaPagoAuto > 0) {
    echo "\n[ADVERTENCIA] El C-006170 ya tiene {$yaPagoAuto} pago(s) automatico(s).\n";
    echo "Presiona CTRL+C si no deseas continuar.\n";
}

echo "\nSe aplicara la correccion completa del refinanciamiento.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 2. Aplicar ───
$pdo->beginTransaction();
try {
    $fechaRefin = $nuevo->FechaGeneracion;
    $sedeId = $nuevo->SedeID;
    $usuario = 'SISTEMA (correccion refinanciamiento)';

    // 2a. Marcar proposicion nueva como refinanciamiento
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $nuevo->ProposicionCreditoID)
        ->update([
            'EsRefinanciamiento' => 1,
            'ProposicionCreditoAnteriorID' => $anterior->ProposicionCreditoID,
        ]);

    // 2b. Crear pago automatico en el credito ANTERIOR (C-006170)
    $pagoID = DB::table('pago')->insertGetId([
        'CreditoID' => $anterior->CreditoID,
        'CuotaID' => null,
        'MontoPagado' => (float) $anterior->SaldoPendiente,
        'FechaPago' => $fechaRefin,
        'TipoPago' => 'EFECTIVO',
        'TipoConcepto' => 'C',
        'EsMora' => false,
        'EsPagoAutomatico' => 1,
        'EsPagoAMayor' => false,
        'EsPagoAMayorPorMora' => false,
        'EsPagoForzado' => false,
        'PromotorCobradorID' => null,
        'Comentario' => 'Pago automático por refinanciamiento. Proposición #' . $nuevo->ProposicionCreditoID . '. Saldo total: S/ ' . number_format((float) $anterior->SaldoPendiente, 2),
        'UsuarioRegistro' => $usuario,
        'Activo' => true,
        'SedeID' => $sedeId,
        'SolicitudResolucionID' => null,
        'PagoOrigenID' => null,
        'FechaCreacion' => now(),
        'FechaModificacion' => null,
    ]);
    echo "  Pago automatico creado: PagoID={$pagoID} | S/{$anterior->SaldoPendiente} | {$fechaRefin}\n";

    // 2c. Cuotas pendientes del C-006170 -> PAGADO
    $nCuotas = DB::table('cuota')
        ->where('CreditoID', $anterior->CreditoID)
        ->where('Activo', 1)
        ->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA', 'NORMAL'])
        ->update([
            'Estado' => 'PAGADO',
            'FechaPago' => $fechaRefin,
            'FechaModificacion' => now(),
        ]);
    echo "  Cuotas marcadas PAGADO: {$nCuotas}\n";

    // 2d. Proposicion anterior: FueRefinanciada=1, SaldoPendiente=0
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $anterior->ProposicionCreditoID)
        ->update([
            'FueRefinanciada' => 1,
            'SaldoPendiente' => 0,
        ]);

    // 2e. Credito anterior: SALDADO
    DB::table('Credito')
        ->where('CreditoID', $anterior->CreditoID)
        ->update([
            'EstatusCreditoFinal' => 'SALDADO',
            'FechaSaldamiento' => $fechaRefin,
        ]);

    // 2f. Recalcular saldo del nuevo credito (por si el observer no corrio)
    \App\Services\SaldoPendienteService::recalcular((int) $nuevo->ProposicionCreditoID);

    // 2g. Logs
    $ahora = now();
    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_REFIN',
        'modelo' => 'ProposicionCredito',
        'modelo_id' => $nuevo->ProposicionCreditoID,
        'old_values' => json_encode(['EsRefinanciamiento' => 0, 'ProposicionCreditoAnteriorID' => null]),
        'new_values' => json_encode(['EsRefinanciamiento' => 1, 'ProposicionCreditoAnteriorID' => $anterior->ProposicionCreditoID, 'Motivo' => 'Correccion: C-006889 debio ser refinanciamiento del C-006170']),
        'created_at' => $ahora,
        'SedeID' => $sedeId,
    ]);
    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_REFIN',
        'modelo' => 'Pago',
        'modelo_id' => $pagoID,
        'old_values' => json_encode(['no existia']),
        'new_values' => json_encode(['Pago automatico refinanciamiento C-006170 -> C-006889', 'Monto' => $anterior->SaldoPendiente]),
        'created_at' => $ahora,
        'SedeID' => $sedeId,
    ]);
    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_REFIN',
        'modelo' => 'Credito',
        'modelo_id' => $anterior->CreditoID,
        'old_values' => json_encode(['EstatusCreditoFinal' => 'ACTIVO', 'SaldoPendiente' => $anterior->SaldoPendiente]),
        'new_values' => json_encode(['EstatusCreditoFinal' => 'SALDADO', 'SaldoPendiente' => 0, 'FueRefinanciada' => 1]),
        'created_at' => $ahora,
        'SedeID' => $sedeId,
    ]);

    $pdo->commit();
    echo "\nAPLICADO CORRECTAMENTE.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// ─── 3. Verificacion ───
echo "\n=== VERIFICACION ===\n";
$nuevo2 = DB::table('ProposicionCredito')->where('ProposicionCreditoID', $nuevo->ProposicionCreditoID)->first();
echo "C-006889: EsRefinanciamiento={$nuevo2->EsRefinanciamiento} | PropAnterior={$nuevo2->ProposicionCreditoAnteriorID}\n";
$ant2 = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('pc.CodigoCredito', 'C-006170')
    ->select('c.EstatusCreditoFinal', 'c.FechaSaldamiento', 'pc.SaldoPendiente', 'pc.FueRefinanciada')
    ->first();
echo "C-006170: Estatus={$ant2->EstatusCreditoFinal} | Saldado={$ant2->FechaSaldamiento} | Saldo={$ant2->SaldoPendiente} | FueRefinanciada={$ant2->FueRefinanciada}\n";
$pagoAuto = DB::table('pago')->where('CreditoID', $anterior->CreditoID)->where('EsPagoAutomatico', 1)->get();
foreach ($pagoAuto as $p) echo "  PagoAuto: PagoID={$p->PagoID} | S/{$p->MontoPagado} | {$p->FechaPago} | " . substr($p->Comentario ?? '', 0, 70) . "\n";
$pend = DB::table('cuota')->where('CreditoID', $anterior->CreditoID)->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA', 'NORMAL'])->count();
echo "Cuotas pendientes restantes del C-006170: {$pend} (debe ser 0)\n";
$logs = DB::table('logs')->where('accion', 'CORR_REFIN')->count();
echo "Logs CORR_REFIN: {$logs}\n";
echo "\n============================================================\n";
echo "  COMPLETADO\n";
echo "============================================================\n";
