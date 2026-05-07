<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fecha = '2026-05-06';
$sedeId = 2;

$extornosQuery = \App\Models\SolicitudResolucionExcedente::withoutGlobalScopes()
    ->where('Estado', 'APROBADA')
    ->whereDate('updated_at', $fecha);

if ($sedeId) {
    $extornosQuery->where('SedeID', $sedeId);
}

$extornos = $extornosQuery
    ->with(['clienteOrigen', 'excedente'])
    ->orderBy('SolicitudID', 'asc')
    ->get();

echo "Count: " . $extornos->count() . "\n";
foreach($extornos as $e) {
    echo "ID: {$e->SolicitudID} | Monto: {$e->MontoAplicar}\n";
}
