<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sedeNombre = 'Trujillo'; // Puedes cambiar esto por Chiclayo o Gerencia
$sede = App\Models\Sede::where('Nombre', 'like', "%{$sedeNombre}%")->first();

if (!$sede) {
    die("Sede no encontrada.");
}

echo "============================================\n";
echo "  REPORTE HISTÓRICO DE FONDOS - {$sede->Nombre}\n";
echo "============================================\n\n";

// 1. INGRESOS POR REMESAS / INYECCIONES (Histórico)
// Asumimos que los MovimientosFondo tienen el historial si es que se ha usado, 
// pero vamos a calcular desde las tablas principales para ser precisos con lo viejo.

$remesasRecibidas = App\Models\TransferenciaSede::where('SedeDestinoID', $sede->SedeID)
    ->where('Estado', 'ACEPTADO')
    ->sum('Monto');

// 2. SALIDAS POR CRÉDITOS (Monto puro prestado)
// Unimos Credito con ProposicionCredito para obtener el MontoTotal prestado (sin intereses)
$prestadoCapital = App\Models\Credito::withoutGlobalScopes()
    ->whereHas('proposicion', function($q) use ($sede) {
        $q->where('SedeID', $sede->SedeID);
    })
    ->where('Activo', true)
    ->with('proposicion')
    ->get()
    ->sum(function($c) {
        return $c->proposicion->MontoTotal;
    });

$interesesEsperados = App\Models\Credito::withoutGlobalScopes()
    ->whereHas('proposicion', function($q) use ($sede) {
        $q->where('SedeID', $sede->SedeID);
    })
    ->where('Activo', true)
    ->get()
    ->sum(function($c) {
        return $c->proposicion->MontoInteres;
    });

// 3. INGRESOS POR PAGOS (Dinero real que regresó)
// Los pagos incluyen tanto capital como interés
$pagosRecaudados = App\Models\Pago::where('SedeID', $sede->SedeID)
    ->where('Activo', true)
    ->sum('MontoPagado');

// 4. SALIDAS POR GASTOS Y COMPRAS (Afectan a Caja Chica usualmente, pero lo calculamos)
$gastosTotales = App\Models\Gasto::where('SedeID', $sede->SedeID)->where('Activo', true)->sum('Total');
$comprasTotales = App\Models\Compra::where('SedeID', $sede->SedeID)->where('Activo', true)->sum('Total');

// CÁLCULO DE SALDO TEÓRICO CAJA ABIERTA
$saldoTeoricoCA = $remesasRecibidas - $prestadoCapital + $pagosRecaudados;

echo "--- RESUMEN DE PRÉSTAMOS ---\n";
echo "Total de Capital Prestado a clientes (Dinero que salió) : S/ " . number_format($prestadoCapital, 2) . "\n";
echo "Total de Intereses Esperados (De esos préstamos)        : S/ " . number_format($interesesEsperados, 2) . "\n";
echo "Total Global Esperado (Capital + Interés)               : S/ " . number_format($prestadoCapital + $interesesEsperados, 2) . "\n\n";

echo "--- RESUMEN DE PAGOS (RECAUDACIÓN) ---\n";
echo "Total Dinero Real Recaudado por Pagos                   : S/ " . number_format($pagosRecaudados, 2) . "\n\n";

echo "--- RESUMEN DE REMESAS ---\n";
echo "Total Remesas/Capital Recibido de Gerencia              : S/ " . number_format($remesasRecibidas, 2) . "\n\n";

echo "--- RESUMEN CAJA CHICA (GASTOS) ---\n";
echo "Total Gastos Administrativos                            : S/ " . number_format($gastosTotales, 2) . "\n";
echo "Total Compras Realizadas                                : S/ " . number_format($comprasTotales, 2) . "\n\n";

echo "============================================\n";
echo "  SALDO TEÓRICO ACTUAL (Si empezaron en 0)\n";
echo "============================================\n";
echo "Fórmula: (Remesas Recibidas) - (Capital Prestado) + (Pagos Recaudados)\n";
echo "Saldo Teórico en Caja Abierta                           : S/ " . number_format($saldoTeoricoCA, 2) . "\n";
echo "============================================\n";

