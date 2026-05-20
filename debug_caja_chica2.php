<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TODOS LOS MOVIMIENTOS DE SEDE 2 (Trujillo) ===\n";
$movimientos = DB::table('movimientos_fondo')
    ->where('SedeID', 2)
    ->orderBy('created_at')
    ->get();

$saldoCC = 0;
echo "\n--- Movimientos que afectan CAJA CHICA ---\n";
foreach ($movimientos as $m) {
    $afecta = false;
    $cambio = 0;
    $nota = '';
    
    if ($m->Tipo === 'INGRESO_CAJA_CHICA') {
        $cambio = $m->Monto;
        $afecta = true;
    } elseif ($m->Tipo === 'EGRESO_CAJA_CHICA') {
        $cambio = $m->Monto;
        $afecta = true;
    } elseif ($m->Tipo === 'TRASLADO_CA_A_CC') {
        $cambio = abs($m->Monto);
        $afecta = true;
    } elseif ($m->Tipo === 'TRASLADO_CC_A_CA') {
        $cambio = -abs($m->Monto);
        $afecta = true;
    } elseif ($m->Tipo === 'RECEPCION_TRANSFERENCIA' && $m->TransferenciaID) {
        $t = DB::table('transferencia_sedes')->where('TransferenciaID', $m->TransferenciaID)->first();
        if ($t && $t->CuentaDestino === 'CAJA_CHICA') {
            $cambio = $m->Monto;
            $afecta = true;
            $nota = " [CuentaDestino={$t->CuentaDestino}]";
        }
    } elseif ($m->Tipo === 'ENVIO_TRANSFERENCIA' && $m->TransferenciaID) {
        $t = DB::table('transferencia_sedes')->where('TransferenciaID', $m->TransferenciaID)->first();
        if ($t && $t->CuentaOrigen === 'CAJA_CHICA') {
            $cambio = $m->Monto;
            $afecta = true;
            $nota = " [CuentaOrigen={$t->CuentaOrigen}]";
        }
    }
    
    if ($afecta) {
        $saldoCC += $cambio;
        echo sprintf("  %s | %-25s | Monto: %10.2f | Cambio CC: %10.2f | Acum CC: %10.2f | %s%s\n",
            $m->created_at, $m->Tipo, $m->Monto, $cambio, $saldoCC, $m->Observacion, $nota);
    }
}

echo "\n=== SALDO CC RECONSTRUIDO DESDE MOVIMIENTOS: {$saldoCC} ===\n";
echo "=== SALDO CC REAL EN fondo_sedes: " . DB::table('fondo_sedes')->where('SedeID', 2)->value('SaldoCajaChica') . " ===\n";
$diferencia = DB::table('fondo_sedes')->where('SedeID', 2)->value('SaldoCajaChica') - $saldoCC;
echo "=== DIFERENCIA (falta un movimiento de inicio): {$diferencia} ===\n";

// Check transfers to CAJA_CHICA
echo "\n=== TRANSFERENCIAS CON CuentaDestino=CAJA_CHICA o CuentaOrigen=CAJA_CHICA ===\n";
$transfers = DB::table('transferencia_sedes')
    ->where(function($q) {
        $q->where('CuentaDestino', 'CAJA_CHICA')
          ->orWhere('CuentaOrigen', 'CAJA_CHICA');
    })
    ->orderBy('created_at')
    ->get();

foreach ($transfers as $t) {
    echo sprintf("  ID:%d | Origen:%d(%s) -> Destino:%d(%s) | Monto:%.2f | Estado:%s | Fecha:%s | FechaResp:%s\n",
        $t->TransferenciaID, $t->SedeOrigenID, $t->CuentaOrigen, 
        $t->SedeDestinoID, $t->CuentaDestino, $t->Monto,
        $t->Estado, $t->FechaTransferencia, $t->FechaRespuesta ?? 'NULL');
}

// Check if FondoSede was updated directly via updated_at history
echo "\n=== HISTORIAL fondo_sedes Sede 2 ===\n";
$fondo = DB::table('fondo_sedes')->where('SedeID', 2)->first();
echo "  created_at: {$fondo->created_at}\n";
echo "  updated_at: {$fondo->updated_at}\n";
echo "  Saldo: {$fondo->Saldo}\n";
echo "  SaldoCajaChica: {$fondo->SaldoCajaChica}\n";

echo "\nDone.\n";
