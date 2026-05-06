<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sede = \App\Models\Sede::where('Nombre', 'like', '%Gerencia%')->first();
if ($sede) {
    \App\Models\FondoSede::updateOrCreate(
        ['SedeID' => $sede->SedeID],
        ['Saldo' => 0]
    );
    echo "Registro de FondoSede restaurado para Gerencia (ID: {$sede->SedeID})";
} else {
    echo "No se encontró la sede de Gerencia.";
}
