<?php
require '../vendor/autoload.php';
$app = require '../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MOVIMIENTOS (Trujillo) ===" . PHP_EOL;
$trujillo = App\Models\Sede::where('Nombre', 'like', '%Trujillo%')->first();
if ($trujillo) {
    $movs = App\Models\MovimientoFondo::where('SedeID', $trujillo->SedeID)
        ->orderBy('MovimientoID', 'desc')
        ->limit(10)
        ->get();
    foreach ($movs as $m) {
        echo "[{$m->MovimientoID}] {$m->Tipo} | Monto: {$m->Monto} | {$m->SaldoAnterior} -> {$m->SaldoNuevo} | {$m->Observacion}" . PHP_EOL;
    }
}

echo PHP_EOL . "=== CREDITOS RECIENTES ===" . PHP_EOL;
$creditos = App\Models\Credito::withoutGlobalScopes()->orderBy('CreditoID', 'desc')->limit(3)->get();
foreach ($creditos as $c) {
    $prop = $c->proposicion;
    echo "[CreditoID:{$c->CreditoID}] MontoTotal: " . ($prop->MontoTotal ?? 'N/A') . " | SedeID: {$c->SedeID} | Fecha: {$c->FechaGeneracion}" . PHP_EOL;
}
