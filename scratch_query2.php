<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$nombre = 'RODRIGUEZ AVALOS JOSE PAULINO';
$cliente = \App\Models\Cliente::where('NombresApellidos', 'like', "%RODRIGUEZ AVALOS%")->first();

if (!$cliente) {
    echo "Cliente no encontrado.\n";
    exit;
}

echo "ClienteID: " . $cliente->ClienteID . "\n";

$solicitudes = \App\Models\SolicitudResolucionExcedente::where('ClienteOrigenID', $cliente->ClienteID)
    ->orWhere('ClienteDestinoID', $cliente->ClienteID)
    ->get();

echo "\n--- Solicitudes de Resolución ---\n";
foreach($solicitudes as $s) {
    echo "ID: {$s->SolicitudID} | Tipo: {$s->TipoResolucion} | Monto: S/ {$s->MontoAplicar} | Estado: {$s->Estado}\n";
}

$excedentes = \App\Models\Excedente::where('ClienteOrigenID', $cliente->ClienteID)->get();
echo "\n--- Excedentes ---\n";
foreach($excedentes as $e) {
    echo "ID: {$e->ExcedenteID} | Tipo: {$e->TipoExcedente} | MontoActual: S/ {$e->Monto} | Estado: {$e->EstadoResolucion}\n";
}

$pagos = \App\Models\Pago::whereHas('credito.proposicion', function($q) use ($cliente) {
    $q->where('ClienteID', $cliente->ClienteID);
})->orderBy('PagoID', 'desc')->take(5)->get();

echo "\n--- Últimos 5 Pagos ---\n";
foreach($pagos as $p) {
    echo "PagoID: {$p->PagoID} | Monto: S/ {$p->MontoPagado} | EsPagoAMayor: " . ($p->EsPagoAMayor ? 'Si' : 'No') . " | SolicitudID: {$p->SolicitudResolucionID} | SedeID: {$p->SedeID}\n";
}
