<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pago;

$sum = Pago::whereHas('credito.proposicion', function($q) {
    $q->where('ClienteID', 34);
})->where('Activo', 1)->sum('MontoPagado');

echo "Total pagado por el cliente 34: " . $sum . "\n";

$pagos = Pago::whereHas('credito.proposicion', function($q) {
    $q->where('ClienteID', 34);
})->where('Activo', 1)->orderBy('FechaPago', 'asc')->get(['PagoID', 'MontoPagado', 'FechaPago', 'Comentario']);

foreach ($pagos as $pago) {
    echo "ID: {$pago->PagoID} | Fecha: {$pago->FechaPago} | Monto: {$pago->MontoPagado} | Comentario: {$pago->Comentario}\n";
}
