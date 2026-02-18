<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AperturaCierreDia;
use App\Models\Mora;

echo "====== TEST EVENTO MORA - VERSIÓN CORREGIDA ======\n\n";

$fechaPrueba = date('Y-m-d', strtotime('+1 day'));

echo "1. CERRANDO TODOS LOS DÍAS ABIERTOS...\n";
$diasAbiertos = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->get();
echo "   Días abiertos encontrados: " . $diasAbiertos->count() . "\n";

foreach ($diasAbiertos as $dia) {
    $dia->update([
        'EstadoDia' => 'CERRADO',
        'FechaCierre' => now()
    ]);
    echo "   ✓ Cerrado: Fecha {$dia->Fecha}\n";
}
echo "\n";

// Limpiar moras previas
Mora::whereDate('FechaMora', $fechaPrueba)->delete();

echo "2. CREANDO NUEVO DÍA ABIERTO ({$fechaPrueba})...\n";
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

echo "\n3. ESPERANDO 2 SEGUNDOS...\n";
sleep(2);

echo "\n4. VERIFICANDO SI SE CREARON MORAS...\n";
$morasNuevas = Mora::whereDate('FechaMora', $fechaPrueba)->count();
echo "   Moras creadas: {$morasNuevas}\n";

if ($morasNuevas > 0) {
    echo "   ✅ EVENTO FUNCIONANDO!\n";
    $morasDetalle = Mora::whereDate('FechaMora', $fechaPrueba)->get();
    foreach ($morasDetalle as $mora) {
        echo "   - Crédito ID {$mora->CreditoID}: S/. {$mora->MontoMora} (Acumulado: S/. {$mora->MoraAcumulada})\n";
    }
} else {
    echo "   ❌ SIN MORAS\n";
    
    // Debug
    $creditosVencidos = \App\Models\Credito::where('Activo', 1)
        ->whereDate('FechaVencimiento', '<=', now())
        ->count();
    echo "   Créditos vencidos: {$creditosVencidos}\n";
}

echo "\n5. REVISANDO LOGS (últimos 15 registros relevantes)...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $allLines = file($logFile);
    $lastLines = array_slice($allLines, -50);
    $count = 0;
    foreach ($lastLines as $line) {
        if ((strpos($line, '[LISTENER]') !== false || 
             strpos($line, '[JOB]') !== false || 
             strpos($line, 'DiaAbierto') !== false ||
             strpos($line, 'CalcularMora') !== false) && 
            strpos($line, '2026-02-') !== false) {
            echo "   " . trim($line) . "\n";
            $count++;
            if ($count >= 15) break;
        }
    }
}

echo "\n====== FIN TEST ======\n";
?>
