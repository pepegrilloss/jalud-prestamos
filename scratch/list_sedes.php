<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sedes = \App\Models\Sede::all();
foreach ($sedes as $sede) {
    echo $sede->SedeID . " - " . $sede->Nombre . "\n";
}
