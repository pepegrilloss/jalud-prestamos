<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Credito;
use App\Models\Cuota;
use Carbon\Carbon;

echo "=== DEBUG MORA ===\n\n";

// 1. Verificar créditos activos y vencidos
echo "1. CRÉDITOS ACTIVOS Y VENCIDOS:\n";
$hoy = today();
$creditosVencidos = Credito::where('Activo', 1)
    ->whereDate('FechaVencimiento', '<=', $hoy)
    ->count();

echo "   Total créditos activos y vencidos: $creditosVencidos\n\n";

// 2. Verificar todos los créditos activos (independiente de fecha)
echo "2. TODOS LOS CRÉDITOS ACTIVOS:\n";
$creditos = Credito::where('Activo', 1)->get();
echo "   Total: " . $creditos->count() . "\n";
foreach ($creditos->take(5) as $cr) {
    echo "   - CreditoID: {$cr->CreditoID}, FechaVencimiento: {$cr->FechaVencimiento}, Hoy: {$hoy}\n";
}
echo "\n";

// 3. Verificar proposiciones y clientes
echo "3. VERIFICAR CLIENTES Y TASAS DE MORA:\n";
foreach ($creditos->take(3) as $cr) {
    $prop = $cr->proposicion;
    if ($prop) {
        $cliente = $prop->cliente;
        if ($cliente) {
            $tasaMora = $cliente->tasaMora;
            echo "   CreditoID {$cr->CreditoID}: Cliente {$cliente->NombresApellidos}, TasaMoraID: {$cliente->TasaMoraID}, TasaMora Obj: " . ($tasaMora ? $tasaMora->Porcentaje . "%" : "NULL") . "\n";
        } else {
            echo "   CreditoID {$cr->CreditoID}: Sin cliente\n";
        }
    } else {
        echo "   CreditoID {$cr->CreditoID}: Sin proposición\n";
    }
}
echo "\n";

// 4. Verificar cuotas
echo "4. VERIFICAR CUOTAS:\n";
foreach ($creditos->take(3) as $cr) {
    $cuotas = $cr->cuotas;
    echo "   CreditoID {$cr->CreditoID}: Cuotas totales: {$cuotas->count()}\n";
    foreach ($cuotas->take(3) as $cuota) {
        echo "      - Cuota {$cuota->NumeroCuota}: Estado={$cuota->Estado}, Monto={$cuota->MontoCuota}\n";
    }
}
echo "\n";

// 5. Ver logs
echo "5. ÚLTIMOS LOGS:\n";
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = array_slice(file($logFile), -10);
    foreach ($lines as $line) {
        if (strpos($line, 'Mora calculada') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
}

echo "\n=== FIN DEBUG ===\n";
?>
