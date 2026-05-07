<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$trujillo = \App\Models\Sede::where('Nombre', 'like', '%Trujillo%')->first();
$pagos = \App\Models\Pago::with(['credito.proposicion.cliente'])->where('SedeID', $trujillo->SedeID)->where('Activo', true)->where('EsPagoAMayor', true)->get();
echo "--- PAGOS A MAYOR (VIRTUALES) EN TRUJILLO ---\n";
$total = 0;
foreach($pagos as $p) {
    $cliente = $p->credito && $p->credito->proposicion && $p->credito->proposicion->cliente ? $p->credito->proposicion->cliente->NombresApellidos : 'Desconocido';
    echo "PagoID: {$p->PagoID} | Monto: S/ {$p->MontoPagado} | SolicitudID: {$p->SolicitudResolucionID} | Cliente: {$cliente}\n";
    $total += $p->MontoPagado;
}
echo "------------------------------------------\n";
echo "Total: S/ " . number_format($total, 2) . "\n";
