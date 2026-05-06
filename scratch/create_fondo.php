<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sede = \App\Models\Sede::where('Nombre', 'like', '%Gerencia%')->first();
if ($sede) {
    \App\Models\FondoSede::firstOrCreate(['SedeID' => $sede->SedeID], ['Saldo' => 250000]);
    echo "Created FondoSede for " . $sede->Nombre . "\n";
} else {
    echo "Sede Gerencia not found\n";
}

$sedeTrujillo = \App\Models\Sede::where('Nombre', 'like', '%Trujillo%')->first();
if ($sedeTrujillo) {
    \App\Models\FondoSede::firstOrCreate(['SedeID' => $sedeTrujillo->SedeID], ['Saldo' => 0]);
    echo "Created FondoSede for " . $sedeTrujillo->Nombre . "\n";
}
