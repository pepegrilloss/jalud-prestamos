<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\DiaAbierto;
use App\Models\AperturaCierreDia;

echo "====== TEST DISPATCH MANUAL ======\n\n";

// Obtener un día ABIERTO existente
echo "1. OBTENIENDO DÍA ABIERTO...\n";
$dia = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();

if (!$dia) {
    echo "   ❌ No hay día abierto en la BD\n";
    exit;
}

echo "   ✓ Día encontrado: {$dia->Fecha}\n\n";

// Disparar manualmente el evento
echo "2. DISPARANDO EVENTO MANUALMENTE...\n";
DiaAbierto::dispatch($dia);

echo "   ✓ Evento despachado\n\n";

// Esperar y revisar logs
echo "3. REVISANDO LOGS (último 10 segundos)...\n";
sleep(2);
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $allLines = file($logFile);
    $lastLines = array_slice($allLines, -15);
    foreach ($lastLines as $line) {
        if (strpos($line, '[LISTENER]') !== false || strpos($line, '[JOB]') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "   ⚠️  No hay archivo de logs\n";
}

echo "\n====== FIN TEST ======\n";
?>
