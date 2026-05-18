<?php
require '../vendor/autoload.php';
$app = require '../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FondoSede;
use App\Models\MovimientoFondo;
use App\Models\TransferenciaSede;
use App\Models\Credito;
use App\Models\Pago;

echo "Iniciando recalculo...\n";

// 1. Limpiar movimientos y transferencias viejas
MovimientoFondo::truncate();
TransferenciaSede::truncate();

// 2. Setear Gerencia con capital infinito para operar
$gerencia = FondoSede::where('SedeID', 1)->first();
if ($gerencia) {
    $gerencia->update([
        'Saldo' => 1000000, // 1 millón
        'SaldoCajaChica' => 0
    ]);
    echo "Gerencia seteada con 1,000,000\n";
}

// 3. Crear Transferencia Oficial de 200,000 hacia Trujillo
$transferencia = TransferenciaSede::create([
    'SedeOrigenID' => 1,
    'SedeDestinoID' => 2,
    'Monto' => 200000,
    'Estado' => 'ACEPTADO',
    'UsuarioOrigenID' => 1, // asumiendo admin
    'UsuarioRespondeID' => 1,
    'FechaTransferencia' => now(),
    'FechaRespuesta' => now(),
    'CuentaOrigen' => 'CAJA_ABIERTA',
    'CuentaDestino' => 'CAJA_ABIERTA'
]);

MovimientoFondo::create([
    'SedeID' => 2,
    'Tipo' => 'RECEPCION_TRANSFERENCIA',
    'Monto' => 200000,
    'SaldoAnterior' => 0,
    'SaldoNuevo' => 200000,
    'TransferenciaID' => $transferencia->TransferenciaID,
    'UsuarioID' => 1,
    'Observacion' => 'Capital inicial inyectado por Gerencia'
]);

// 4. Calcular prestamos y crear movimientos unificados
$prestadoCapital = Credito::withoutGlobalScopes()
    ->whereHas('proposicion', function($q) { $q->where('SedeID', 2); })
    ->where('Activo', true)
    ->with('proposicion')
    ->get()
    ->sum(function($c) { return $c->proposicion->MontoTotal; });

$saldoActual = 200000 - $prestadoCapital;

MovimientoFondo::create([
    'SedeID' => 2,
    'Tipo' => 'EGRESO_COLOCACION',
    'Monto' => -$prestadoCapital,
    'SaldoAnterior' => 200000,
    'SaldoNuevo' => $saldoActual,
    'UsuarioID' => 1,
    'Observacion' => 'Histórico: Consolidado de todos los créditos emitidos'
]);

// 5. Calcular pagos y crear movimientos unificados
$pagosRecaudados = Pago::where('SedeID', 2)
    ->where('Activo', true)
    ->sum('MontoPagado');

$saldoFinal = $saldoActual + $pagosRecaudados;

MovimientoFondo::create([
    'SedeID' => 2,
    'Tipo' => 'INGRESO_RECAUDO',
    'Monto' => $pagosRecaudados,
    'SaldoAnterior' => $saldoActual,
    'SaldoNuevo' => $saldoFinal,
    'UsuarioID' => 1,
    'Observacion' => 'Histórico: Consolidado de todos los pagos recibidos'
]);

// 6. Actualizar FondoSede Trujillo
$trujillo = FondoSede::where('SedeID', 2)->first();
if ($trujillo) {
    $trujillo->update([
        'Saldo' => $saldoFinal,
        'SaldoCajaChica' => 0
    ]);
}

echo "=================================================\n";
echo " RECALCULO COMPLETADO EXITOSAMENTE\n";
echo "=================================================\n";
echo "Inyección Base        : S/ 200,000.00\n";
echo "Créditos Otorgados    : S/ -" . number_format($prestadoCapital, 2) . "\n";
echo "Pagos Recuperados     : S/ +" . number_format($pagosRecaudados, 2) . "\n";
echo "-------------------------------------------------\n";
echo "SALDO FÍSICO EN CAJA  : S/ " . number_format($saldoFinal, 2) . "\n";
echo "=================================================\n";
