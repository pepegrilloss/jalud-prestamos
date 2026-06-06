<?php
/**
 * Corrige fechas de movimientos de Caja Chica para el reporte del 06/04/2026.
 * 
 * Auto-detecta IDs en lugar de hardcodearlos.
 * Solo afecta registros de SedeID=2 (Trujillo) relacionados con:
 *   - Transferencia de 3,500 de CA → CC
 *   - Gastos y Compras con FechaEmision = 2026-04-06
 *
 * Ejecutar desde raíz del proyecto: php corregir_fechas_caja_chica.php
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$sedeId   = 2;          // Trujillo
$fechaCorrecta = '2026-04-06';
$fechaIncorrecta = '2026-04-07';
$horaBase  = 8;         // hora para el primer movimiento, luego +15min

echo "=== CORRIGIENDO FECHAS CAJA CHICA ===\n";
echo "Sede: {$sedeId} | Fecha correcta: {$fechaCorrecta}\n\n";

// ──────────────────────────────────────────────────────────
// PASO 1: Transferencia 3,500 de CA → CC (auto-transfer Sede 2)
// ──────────────────────────────────────────────────────────
echo "--- PASO 1: Buscando transferencia 3,500 CA→CC ---\n";

$transferencia = DB::table('transferencia_sedes')
    ->where('Monto', 3500)
    ->where('CuentaOrigen', 'CAJA_ABIERTA')
    ->where('CuentaDestino', 'CAJA_CHICA')
    ->where('Estado', 'ACEPTADO')
    ->first();

if (!$transferencia) {
    echo "  [ADVERTENCIA] No se encontró transferencia de 3,500 CA→CC.\n";
} else {
    $fechaRespActual = $transferencia->FechaRespuesta;
    $fechaRespEsperada = $fechaCorrecta . ' ' . str_pad($horaBase, 2, '0', STR_PAD_LEFT) . ':00:00';
    
    if (Carbon::parse($fechaRespActual)->toDateString() === $fechaCorrecta) {
        echo "  Transferencia #{$transferencia->TransferenciaID}: ya tiene FechaRespuesta {$fechaRespActual}. OK, no se modifica.\n";
    } else {
        DB::table('transferencia_sedes')
            ->where('TransferenciaID', $transferencia->TransferenciaID)
            ->update(['FechaRespuesta' => $fechaRespEsperada]);
        echo "  Transferencia #{$transferencia->TransferenciaID}: FechaRespuesta {$fechaRespActual} → {$fechaRespEsperada}\n";
    }

    $tId = $transferencia->TransferenciaID;

    $movsTransferencia = DB::table('movimientos_fondo')
        ->where('TransferenciaID', $tId)
        ->where('SedeID', $sedeId)
        ->get();

    foreach ($movsTransferencia as $mov) {
        $fechaActualMov = $mov->FechaMovimiento ? Carbon::parse($mov->FechaMovimiento)->toDateString() : 'NULL';
        if ($fechaActualMov === $fechaCorrecta) {
            echo "  Mov #{$mov->MovimientoID} ({$mov->Tipo}): ya tiene fecha {$fechaActualMov}. OK.\n";
        } else {
            $nuevaFecha = $fechaCorrecta . ' ' . str_pad($horaBase, 2, '0', STR_PAD_LEFT) . ':00:00';
            DB::table('movimientos_fondo')
                ->where('MovimientoID', $mov->MovimientoID)
                ->update([
                    'FechaMovimiento' => $nuevaFecha,
                    'created_at' => $nuevaFecha,
                ]);
            echo "  Mov #{$mov->MovimientoID} ({$mov->Tipo}): {$fechaActualMov} → {$nuevaFecha}\n";
        }
    }
    
    $horaBase++;
}

// ──────────────────────────────────────────────────────────
// PASO 2: Buscar gastos del 06/04/2026 para Sede 2
// ──────────────────────────────────────────────────────────
echo "\n--- PASO 2: Buscando gastos del {$fechaCorrecta} ---\n";

$gastos = DB::table('Gasto')
    ->where('SedeID', $sedeId)
    ->where('Activo', 1)
    ->whereDate('FechaEmision', $fechaCorrecta)
    ->get(['GastoID', 'Total', 'MetodoGasto']);

foreach ($gastos as $gasto) {
    echo "  Gasto #{$gasto->GastoID}: Total={$gasto->Total} Metodo={$gasto->MetodoGasto}\n";

    $movsGasto = DB::table('movimientos_fondo')
        ->where('SedeID', $sedeId)
        ->whereIn('Tipo', ['EGRESO_CAJA_CHICA', 'INGRESO_CAJA_CHICA'])
        ->where(function ($q) use ($gasto) {
            $q->where('Observacion', 'LIKE', "%Gasto #{$gasto->GastoID}%")
              ->orWhere('Observacion', 'LIKE', "%gasto #{$gasto->GastoID}%");
        })
        ->whereDate('FechaMovimiento', $fechaIncorrecta)
        ->get();

    foreach ($movsGasto as $mov) {
        $nuevaFecha = $fechaCorrecta . ' ' . str_pad($horaBase, 2, '0', STR_PAD_LEFT) . ':00:00';
        DB::table('movimientos_fondo')
            ->where('MovimientoID', $mov->MovimientoID)
            ->update([
                'FechaMovimiento' => $nuevaFecha,
                'created_at' => $nuevaFecha,
            ]);
        echo "    Mov #{$mov->MovimientoID} EGRESO ({$mov->Monto}) → {$nuevaFecha}\n";
        $horaBase++;
        if (($horaBase % 60) >= 60) $horaBase = ($horaBase % 60);
    }
}

// ──────────────────────────────────────────────────────────
// PASO 3: Buscar compras del 06/04/2026 para Sede 2
// ──────────────────────────────────────────────────────────
echo "\n--- PASO 3: Buscando compras del {$fechaCorrecta} ---\n";

$compras = DB::table('Compra')
    ->where('SedeID', $sedeId)
    ->where('Activo', 1)
    ->whereDate('FechaEmision', $fechaCorrecta)
    ->get(['CompraID', 'Total', 'TipoCompra']);

foreach ($compras as $compra) {
    echo "  Compra #{$compra->CompraID}: Total={$compra->Total} Tipo={$compra->TipoCompra}\n";

    $movsCompra = DB::table('movimientos_fondo')
        ->where('SedeID', $sedeId)
        ->whereIn('Tipo', ['EGRESO_CAJA_CHICA', 'INGRESO_CAJA_CHICA'])
        ->where(function ($q) use ($compra) {
            $q->where('Observacion', 'LIKE', "%compra #{$compra->CompraID}%")
              ->orWhere('Observacion', 'LIKE', "%Compra #{$compra->CompraID}%")
              ->orWhere('Observacion', 'LIKE', "%Gasto #{$compra->CompraID}%");
        })
        ->whereDate('FechaMovimiento', $fechaIncorrecta)
        ->get();

    foreach ($movsCompra as $mov) {
        $nuevaFecha = $fechaCorrecta . ' ' . str_pad($horaBase, 2, '0', STR_PAD_LEFT) . ':00:00';
        DB::table('movimientos_fondo')
            ->where('MovimientoID', $mov->MovimientoID)
            ->update([
                'FechaMovimiento' => $nuevaFecha,
                'created_at' => $nuevaFecha,
            ]);
        $tipoCorto = $mov->Tipo === 'INGRESO_CAJA_CHICA' ? 'INGRESO' : 'EGRESO';
        echo "    Mov #{$mov->MovimientoID} {$tipoCorto} ({$mov->Monto}) → {$nuevaFecha}\n";
        $horaBase++;
        if ($horaBase >= 24) $horaBase = 8;
    }
}

// ──────────────────────────────────────────────────────────
// VERIFICACIÓN FINAL
// ──────────────────────────────────────────────────────────
echo "\n==========================================\n";
echo "VERIFICACIÓN FINAL\n";
echo "==========================================\n";

$fechaLimite = $fechaCorrecta . ' 23:59:59';
$movs = DB::table('movimientos_fondo')
    ->where('SedeID', $sedeId)
    ->where(function ($q) use ($fechaLimite) {
        $q->where('FechaMovimiento', '<=', $fechaLimite)
          ->orWhere(function ($q2) use ($fechaLimite) {
              $q2->whereNull('FechaMovimiento')->where('created_at', '<=', $fechaLimite);
          });
    })
    ->orderBy('FechaMovimiento')
    ->get(['MovimientoID', 'Tipo', 'Monto', 'FechaMovimiento', 'Observacion', 'TransferenciaID']);

$saldoCC = 0;
$totalIngresos = 0;
$totalEgresos = 0;

foreach ($movs as $m) {
    if ($m->Tipo === 'INGRESO_CAJA_CHICA') {
        $saldoCC += $m->Monto;
        $totalIngresos += $m->Monto;
    } elseif ($m->Tipo === 'EGRESO_CAJA_CHICA') {
        $saldoCC += $m->Monto;
        $totalEgresos += abs($m->Monto);
    } elseif ($m->Tipo === 'RECEPCION_TRANSFERENCIA' && $m->TransferenciaID) {
        $t = DB::table('transferencia_sedes')->where('TransferenciaID', $m->TransferenciaID)->first();
        if ($t && $t->CuentaDestino === 'CAJA_CHICA') {
            $saldoCC += abs($m->Monto);
            $totalIngresos += abs($m->Monto);
        }
    } elseif ($m->Tipo === 'TRASLADO_CA_A_CC') {
        $saldoCC += abs($m->Monto);
        $totalIngresos += abs($m->Monto);
    } elseif ($m->Tipo === 'TRASLADO_CC_A_CA') {
        $saldoCC -= abs($m->Monto);
        $totalEgresos += abs($m->Monto);
    }
}

// Gastos + Compras del día
$totalGastosDia = DB::table('Gasto')
    ->where('SedeID', $sedeId)->where('Activo', 1)
    ->whereDate('FechaEmision', $fechaCorrecta)->sum('Total');

$totalComprasDia = DB::table('Compra')
    ->where('SedeID', $sedeId)->where('Activo', 1)
    ->whereDate('FechaEmision', $fechaCorrecta)->sum('Total');

$totalGastosCompras = $totalGastosDia + $totalComprasDia;

echo "\nResumen para Sede {$sedeId} al {$fechaCorrecta}:\n";
echo "  Total ingresos a CC:  +" . number_format($totalIngresos, 2) . "\n";
echo "  Total egresos de CC:  -" . number_format($totalEgresos, 2) . "\n";
echo "  Gastos (tabla Gasto): " . number_format($totalGastosDia, 2) . "\n";
echo "  Compras (tabla Compra): " . number_format($totalComprasDia, 2) . "\n";
echo "  Saldo CC calculado:   " . number_format($saldoCC, 2) . "\n";

$esperado = 3500 - $totalGastosCompras;
echo "  Saldo esperado:        " . number_format($esperado, 2) . "\n";

if (abs($saldoCC - $esperado) < 0.01) {
    echo "\n  ✓ CORRECTO: El reporte del {$fechaCorrecta} mostrará:\n";
    echo "    Saldo Inicial Caja Chica:      0.00\n";
    echo "    Ingresos del día (Traslado):   +" . number_format(3500, 2) . "\n";
    echo "    Gastos del día:                -" . number_format($totalGastosCompras, 2) . "\n";
    echo "    Saldo Final Caja Chica:        " . number_format($saldoCC, 2) . "\n";
} else {
    echo "\n  ✗ ERROR: Diferencia de S/ " . number_format(abs($saldoCC - $esperado), 2) . "\n";
    echo "  Revisar manualmente los movimientos.\n";
}

echo "\nDone.\n";
