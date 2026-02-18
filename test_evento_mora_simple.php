<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AperturaCierreDia;
use App\Models\Mora;

echo "====== TEST SIMPLE - SIN LIMPIAR PREVIOS ======\n\n";

$fechaPrueba = '2026-02-20'; // Fecha diferente cada test

echo "1. CREANDO DÍA ABIERTO ({$fechaPrueba})...\n";
try {
    $dia = AperturaCierreDia::create([
        'Fecha' => $fechaPrueba,
        'EstadoDia' => 'ABIERTO',
        'FechaApertura' => now(),
        'UsuarioAperturaID' => 1,
    ]);
    echo "   ✓ Día creado: ID {$dia->AperturaCierreDiaID}\n";
    echo "   Estado: {$dia->EstadoDia}\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit;
}

echo "\n2. ESPERANDO 2 SEGUNDOS...\n";
sleep(2);

echo "\n3. VERIFICANDO ESTADO DEL DÍA...\n";
$diaActual = AperturaCierreDia::find($dia->AperturaCierreDiaID);
echo "   Estado actual: {$diaActual->EstadoDia}\n";
echo "   FechaCierre: {$diaActual->FechaCierre}\n";

echo "\n4. VERIFICANDO MORAS CREADAS...\n";
$morasNuevas = Mora::whereDate('FechaMora', $fechaPrueba)->count();
echo "   Moras creadas: {$morasNuevas}\n";

if ($morasNuevas > 0) {
    $morasDetalle = Mora::whereDate('FechaMora', $fechaPrueba)->get();
    foreach ($morasDetalle as $mora) {
        echo "   - Crédito ID {$mora->CreditoID}: S/. {$mora->MontoMora}\n";
    }
}

echo "\n5. REVISANDO LOGS...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    echo "   Últimos 10 logs con relevancia:\n";
    $allLines = file($logFile);
    $lastLines = array_slice($allLines, -20);
    foreach ($lastLines as $line) {
        if (strpos($line, '[LISTENER]') !== false || strpos($line, '[JOB]') !== false || strpos($line, 'DiaAbierto') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
}

echo "\n====== FIN TEST ======\n";
?>
