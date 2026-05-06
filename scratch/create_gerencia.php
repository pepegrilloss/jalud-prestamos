<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sedeGerencia = \App\Models\Sede::firstOrCreate(
    ['Nombre' => 'Gerencia General'],
    ['Activo' => true, 'Codigo' => 'GER']
);

\App\Models\FondoSede::firstOrCreate(
    ['SedeID' => $sedeGerencia->SedeID],
    ['Saldo' => 250000]
);

echo "Created Gerencia with ID: " . $sedeGerencia->SedeID . "\n";
