<?php
/**
 * CORRIGE MOVIMIENTOS DE FONDO INVERTIDOS de transferencias EsSolicitudGerencia.
 *
 * Confirmado por la señora: Gerencia solicito el dinero a Chiclayo, por lo que
 * FISICAMENTE el dinero salio de CHICLAYO (ENVIO) y llego a GERENCIA (RECEPCION).
 *
 * Las transferencias 108 (S/3,000.00) y 146 (S/796.70) se procesaron con el
 * flujo ESTANDAR (debita SedeOrigenID=Gerencia, acredita SedeDestinoID=Chiclayo)
 * en lugar del flujo de solicitud de gerencia. Los movimientos quedaron invertidos.
 *
 * Correccion:
 *   1. Mover los movimientos ENVIO a Chiclayo y los RECEPCION a Gerencia
 *      (SedeID correcta), con SaldoAnterior/SaldoNuevo tomados de los
 *      movimientos vecinos reales de cada sede.
 *   2. Ajustar FondoSede.Saldo: Chiclayo -7593.40 (3000*2 + 796.70*2),
 *      Gerencia +7593.40.
 *   3. Logs por movimiento y por fondo.
 *
 * NO toca transferencia_sedes (la semantica EsSolicitudGerencia ya es correcta:
 * SedeOrigenID=Gerencia solicita, SedeDestinoID=Chiclayo entrega).
 *
 * Ejecutar: php corregir_movimientos_108_146.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR MOVIMIENTOS INVERTIDOS (Tr 108 y 146)\n";
echo "============================================================\n\n";

$nombre = fn($id) => DB::table('Sede')->where('SedeID', $id)->value('Nombre');

// ─── 1. Estado actual ───
echo "=== ESTADO ACTUAL ===\n";
foreach ([108, 146] as $trId) {
    $t = DB::table('transferencia_sedes')->where('TransferenciaID', $trId)->first();
    echo "Transferencia $trId: {$t->Monto} | {$nombre($t->SedeOrigenID)}->{$nombre($t->SedeDestinoID)} | EsSG={$t->EsSolicitudGerencia}\n";
    $movs = DB::table('movimientos_fondo')->where('TransferenciaID', $trId)->orderBy('MovimientoID')->get();
    foreach ($movs as $m) {
        echo "  MovID={$m->MovimientoID} | {$nombre($m->SedeID)} | {$m->Tipo} | {$m->Monto} | Saldo {$m->SaldoAnterior}->{$m->SaldoNuevo}\n";
    }
}

$fondos = DB::table('fondo_sedes')->whereIn('SedeID', [1, 3])->get();
echo "\nFondoSede actual:\n";
foreach ($fondos as $f) echo "  {$nombre($f->SedeID)}: Saldo={$f->Saldo} | CC={$f->SaldoCajaChica}\n";

// ─── 2. Plan de correccion ───
echo "\n=== PLAN DE CORRECCION ===\n";
echo "  108: ENVIO (9409) -> Chiclayo  Saldo 578847.30 -> 575847.30\n";
echo "       RECEPCION (9410) -> Gerencia Saldo 11808.03 -> 14808.03\n";
echo "  146: ENVIO (18300) -> Chiclayo Saldo 653637.96 -> 652841.26\n";
echo "       RECEPCION (18301) -> Gerencia Saldo 81851.74 -> 82648.44\n";
echo "  FondoSede: Chiclayo " . $fondos->firstWhere('SedeID', 1)->Saldo . " -> " . ($fondos->firstWhere('SedeID', 1)->Saldo - 7593.40) . "\n";
echo "             Gerencia " . $fondos->firstWhere('SedeID', 3)->Saldo . " -> " . ($fondos->firstWhere('SedeID', 3)->Saldo + 7593.40) . "\n";

echo "\nPresiona ENTER para aplicar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 3. Aplicar ───
$pdo->beginTransaction();
try {
    // 3a. Transferencia 108
    $mov108Envio = DB::table('movimientos_fondo')->where('MovimientoID', 9409)->first();
    $mov108Rec = DB::table('movimientos_fondo')->where('MovimientoID', 9410)->first();

    DB::table('movimientos_fondo')->where('MovimientoID', 9409)->update([
        'SedeID' => 1,
        'SaldoAnterior' => 578847.30,
        'SaldoNuevo' => 575847.30,
        'Observacion' => 'Envio a Gerencia por solicitud de gerencia #108 (Chiclayo entrega). Salida de CAJA_ABIERTA.',
    ]);
    DB::table('movimientos_fondo')->where('MovimientoID', 9410)->update([
        'SedeID' => 3,
        'SaldoAnterior' => 11808.03,
        'SaldoNuevo' => 14808.03,
        'Observacion' => 'Recepcion desde Chiclayo por solicitud de gerencia #108. Ingreso a CAJA_ABIERTA.',
    ]);

    // 3b. Transferencia 146
    DB::table('movimientos_fondo')->where('MovimientoID', 18300)->update([
        'SedeID' => 1,
        'SaldoAnterior' => 653637.96,
        'SaldoNuevo' => 652841.26,
        'Observacion' => 'Envio a Gerencia por solicitud de gerencia #146 (Chiclayo entrega). Salida de CAJA_ABIERTA.',
    ]);
    DB::table('movimientos_fondo')->where('MovimientoID', 18301)->update([
        'SedeID' => 3,
        'SaldoAnterior' => 81851.74,
        'SaldoNuevo' => 82648.44,
        'Observacion' => 'Recepcion desde Chiclayo por solicitud de gerencia #146. Ingreso a CAJA_ABIERTA.',
    ]);

    // 3c. FondoSede
    DB::table('fondo_sedes')->where('SedeID', 1)->update(['Saldo' => DB::raw('Saldo - 7593.40')]);
    DB::table('fondo_sedes')->where('SedeID', 3)->update(['Saldo' => DB::raw('Saldo + 7593.40')]);

    // 3d. Logs
    $ahora = now();
    foreach ([9409, 9410, 18300, 18301] as $movId) {
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_MOV',
            'modelo' => 'MovimientoFondo',
            'modelo_id' => $movId,
            'old_values' => json_encode(['SedeID' => $movId <= 9410 ? 3 : 3, 'Detalle' => 'Sede invertida por flujo estandar']),
            'new_values' => json_encode(['SedeID' => $movId <= 9410 ? ($movId == 9409 ? 1 : 3) : ($movId == 18300 ? 1 : 3), 'Motivo' => 'EsSolicitudGerencia: la sede entrega a Gerencia']),
            'created_at' => $ahora,
            'SedeID' => $movId == 9409 || $movId == 18300 ? 1 : 3,
        ]);
    }

    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_FONDO',
        'modelo' => 'FondoSede',
        'modelo_id' => 1,
        'old_values' => json_encode(['Saldo' => $fondos->firstWhere('SedeID', 1)->Saldo]),
        'new_values' => json_encode(['Saldo' => $fondos->firstWhere('SedeID', 1)->Saldo - 7593.40, 'Motivo' => 'Movimientos invertidos Tr 108 y 146 corregidos']),
        'created_at' => $ahora,
        'SedeID' => 1,
    ]);
    DB::table('logs')->insert([
        'user_id' => 0,
        'accion' => 'CORR_FONDO',
        'modelo' => 'FondoSede',
        'modelo_id' => 3,
        'old_values' => json_encode(['Saldo' => $fondos->firstWhere('SedeID', 3)->Saldo]),
        'new_values' => json_encode(['Saldo' => $fondos->firstWhere('SedeID', 3)->Saldo + 7593.40, 'Motivo' => 'Movimientos invertidos Tr 108 y 146 corregidos']),
        'created_at' => $ahora,
        'SedeID' => 3,
    ]);

    $pdo->commit();
    echo "\nAPLICADO CORRECTAMENTE.\n";
} catch (\Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// ─── 4. Verificacion ───
echo "\n=== VERIFICACION ===\n";
foreach ([108, 146] as $trId) {
    $movs = DB::table('movimientos_fondo')->where('TransferenciaID', $trId)->orderBy('MovimientoID')->get();
    foreach ($movs as $m) {
        echo "  Tr$trId MovID={$m->MovimientoID} | {$nombre($m->SedeID)} | {$m->Tipo} | {$m->Monto} | Saldo {$m->SaldoAnterior}->{$m->SaldoNuevo}\n";
    }
}
$fondos2 = DB::table('fondo_sedes')->whereIn('SedeID', [1, 3])->get();
foreach ($fondos2 as $f) echo "  FondoSede {$nombre($f->SedeID)}: Saldo={$f->Saldo}\n";
echo "\nLogs: CORR_MOV=" . DB::table('logs')->where('accion', 'CORR_MOV')->count() . " | CORR_FONDO=" . DB::table('logs')->where('accion', 'CORR_FONDO')->count() . "\n";
echo "\n============================================================\n";
echo "  COMPLETADO\n";
echo "============================================================\n";
