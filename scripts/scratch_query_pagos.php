<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pagos = \App\Models\Pago::where('EsPagoAMayor', true)->get();
foreach($pagos as $p) {
    echo "PagoID: {$p->PagoID} | created_at: {$p->created_at} | FechaPago: {$p->FechaPago}\n";
}
