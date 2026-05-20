<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Get sedes
echo "=== SEDES ===\n";
$sedes = DB::table('Sede')->select('SedeID', 'Nombre')->get();
foreach ($sedes as $s) {
    echo "  SedeID: {$s->SedeID} - {$s->Nombre}\n";
}

// 2. Get current FondoSede balances
echo "\n=== FONDO SEDES (Saldos actuales) ===\n";
$fondos = DB::table('fondo_sedes')->get();
foreach ($fondos as $f) {
    echo "  SedeID: {$f->SedeID} | Saldo CA: {$f->Saldo} | Saldo CC: {$f->SaldoCajaChica}\n";
}

// 3. Check MovimientoFondo types for Caja Chica up to 19/05/2026 end of day
$fechaLimite = '2026-05-19 23:59:59';
echo "\n=== MOVIMIENTOS FONDO (hasta {$fechaLimite}) - Tipos que afectan Caja Chica ===\n";

$movimientos = DB::table('movimientos_fondo')
    ->where('created_at', '<=', $fechaLimite)
    ->orderBy('created_at')
    ->get();

$saldoCC = [];
foreach ($movimientos as $m) {
    $sedeId = $m->SedeID;
    if (!isset($saldoCC[$sedeId])) {
        $saldoCC[$sedeId] = 0;
    }
    
    $afecta = false;
    $cambio = 0;
    
    if ($m->Tipo === 'INGRESO_CAJA_CHICA') {
        $cambio = $m->Monto;
        $afecta = true;
    } elseif ($m->Tipo === 'EGRESO_CAJA_CHICA') {
        $cambio = $m->Monto; // already negative
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
        }
    } elseif ($m->Tipo === 'ENVIO_TRANSFERENCIA' && $m->TransferenciaID) {
        $t = DB::table('transferencia_sedes')->where('TransferenciaID', $m->TransferenciaID)->first();
        if ($t && $t->CuentaOrigen === 'CAJA_CHICA') {
            $cambio = $m->Monto; // already negative
            $afecta = true;
        }
    }
    
    if ($afecta) {
        $saldoCC[$sedeId] += $cambio;
        echo "  Sede:{$sedeId} | Tipo: {$m->Tipo} | Monto: {$m->Monto} | Cambio CC: {$cambio} | Saldo CC acum: {$saldoCC[$sedeId]} | Obs: {$m->Observacion} | Fecha: {$m->created_at}\n";
    }
}

echo "\n=== SALDO CC CALCULADO POR SEDE (hasta 19/05/2026) ===\n";
foreach ($saldoCC as $sedeId => $saldo) {
    echo "  SedeID: {$sedeId} => Saldo CC: {$saldo}\n";
}

// 4. Now check Gastos and Compras for the sede
echo "\n=== GASTOS (todos, por sede) ===\n";
$gastos = DB::table('Gasto')
    ->where('Activo', true)
    ->orderBy('FechaEmision')
    ->get();
foreach ($gastos as $g) {
    echo "  GastoID:{$g->GastoID} | Sede:{$g->SedeID} | Total:{$g->Total} | FechaEmision:{$g->FechaEmision} | FechaCreacion:{$g->FechaCreacion}\n";
}

echo "\n=== COMPRAS (todas, por sede) ===\n";
$compras = DB::table('Compra')
    ->where('Activo', true)
    ->orderBy('FechaEmision')
    ->get();
foreach ($compras as $c) {
    echo "  CompraID:{$c->CompraID} | Sede:{$c->SedeID} | Total:{$c->Total} | FechaEmision:{$c->FechaEmision} | FechaCreacion:{$c->FechaCreacion}\n";
}

echo "\n=== RESUMEN GASTOS + COMPRAS POR SEDE ===\n";
$totalGastosPorSede = DB::table('Gasto')->where('Activo', true)->select('SedeID', DB::raw('SUM(Total) as total'))->groupBy('SedeID')->get();
$totalComprasPorSede = DB::table('Compra')->where('Activo', true)->select('SedeID', DB::raw('SUM(Total) as total'))->groupBy('SedeID')->get();

echo "Gastos:\n";
foreach ($totalGastosPorSede as $r) {
    echo "  SedeID: {$r->SedeID} => Total Gastos: {$r->total}\n";
}
echo "Compras:\n";
foreach ($totalComprasPorSede as $r) {
    echo "  SedeID: {$r->SedeID} => Total Compras: {$r->total}\n";
}

echo "\nDone.\n";
