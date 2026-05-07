<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$solicitudes = \App\Models\SolicitudResolucionExcedente::with(['clienteOrigen', 'excedente', 'creditoDestino.proposicion'])
    ->where('Estado', 'APROBADA')
    ->get();

foreach($solicitudes as $s) {
    $codigoCredito = $s->creditoDestino?->proposicion?->CodigoCredito ?? 'N/A';
    $clienteNombre = $s->clienteOrigen?->NombresApellidos ?? 'N/A';
    echo "ID: {$s->SolicitudID} | Tipo: {$s->TipoResolucion} | Monto: {$s->MontoAplicar} | CreditoDest: {$codigoCredito} | NroOp: " . ($s->excedente?->NroOperacion ?? '') . " | Cliente: {$clienteNombre} | created: {$s->created_at}\n";
}
