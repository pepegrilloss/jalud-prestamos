<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AperturaCierreDia;
use App\Models\Mora;
use App\Models\Credito;
use App\Models\Cuota;
use Illuminate\Support\Facades\Log;

echo "====== TEST DE INTEGRACIÓN EVENTO MORA ======\n\n";

// 1. Limpiar datos de prueba
echo "1. LIMPIANDO DATOS PREVIOS (si existen)...\n";
$fechaPrueba = '2026-02-19';

// Cerrar cualquier día abierto
$diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();
if ($diaAbierto) {
    $diaAbierto->update(['EstadoDia' => 'CERRADO', 'FechaCierre' => now()]);
    echo "   ✓ Día abierto anterior cerrado\n";
}

// Eliminar datos de prueba
AperturaCierreDia::whereDate('Fecha', $fechaPrueba)->delete();
Mora::whereDate('FechaMora', $fechaPrueba)->delete();
echo "   ✓ Datos anteriores eliminados\n\n";

// DEBUG: Verificar créditos vencidos existentes
echo "2. VERIFICANDO CRÉDITOS EN EL SISTEMA...\n";
$creditosVencidos = Credito::where('Activo', 1)
    ->whereDate('FechaVencimiento', '<=', now())
    ->count();
echo "   Créditos vencidos hoy: {$creditosVencidos}\n";

if ($creditosVencidos === 0) {
    echo "   ⚠️  NO HAY CRÉDITOS VENCIDOS EN LA BD\n";
    echo "   CREANDO CRÉDITO DE PRUEBA VENCIDO...\n";
    
    // Obtener o crear cliente
    $cliente = \App\Models\Cliente::first();
    if (!$cliente) {
        echo "   ❌ ERROR: No hay clientes en la base de datos\n";
        exit;
    }
    echo "   ✓ Cliente encontrado: {$cliente->DNI}\n";
    
    // Crear proposición de crédito si no existe
    $proposicion = $cliente->proposiciones()->first();
    if (!$proposicion) {
        $proposicion = $cliente->proposiciones()->create([
            'MontoSolicitado' => 10000,
            'TasaInteres' => 0.05,
            'SaldoPendiente' => 5000,
            'Estado' => 'APROBADA'
        ]);
    }
    echo "   ✓ Proposición: ID {$proposicion->ProposicionCreditoID}\n";
    
    // Crear crédito vencido
    $credito = Credito::create([
        'ProposicionCreditoID' => $proposicion->ProposicionCreditoID,
        'FechaVencimiento' => now()->subDay(), // Vence ayer
        'Activo' => 1,
    ]);
    echo "   ✓ Crédito creado vencido: ID {$credito->CreditoID} (Vence: {$credito->FechaVencimiento->format('Y-m-d')})\n";
}
echo "\n";

// 2. Crear un nuevo día
echo "3. CREANDO NUEVO DÍA ABIERTO ({$fechaPrueba})...\n";
try {
    $dia = AperturaCierreDia::create([
        'Fecha' => $fechaPrueba,
        'EstadoDia' => 'ABIERTO',
        'FechaApertura' => now(),
        'UsuarioAperturaID' => 1,
    ]);
    echo "   ✓ Día creado: ID {$dia->AperturaCierreDiaID}\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

// 3. Verificar logs
echo "4. REVISANDO LOGS GENERADOS...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lastLines = array_slice(file($logFile), -10);
    foreach ($lastLines as $line) {
        if (strpos($line, 'CalcularMora') !== false || strpos($line, 'DiaAbierto') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "   ⚠️  No hay archivo de logs\n";
}
echo "\n";

// 4. Verificar si se disparó el evento (revisar moras creadas)
echo "5. VERIFICANDO SI SE CALCULÓ MORA...\n";
sleep(1);
$morasNuevas = Mora::whereDate('FechaMora', $fechaPrueba)->count();
echo "   Moras creadas en {$fechaPrueba}: {$morasNuevas}\n";

if ($morasNuevas > 0) {
    echo "   ✅ EVENTO FUNCIONANDO - Se crearon {$morasNuevas} moras\n";
    
    $morasDetalle = Mora::whereDate('FechaMora', $fechaPrueba)->get();
    foreach ($morasDetalle as $mora) {
        echo "   - Crédito ID {$mora->CreditoID}: S/. {$mora->MontoMora} (Acumulado: S/. {$mora->MoraAcumulada})\n";
    }
} else {
    echo "   ❌ SIN MORAS - Verificando por qué...\n";
    
    // Debug: revisar créditos vencidos de nuevo
    $creditosPrueba = Credito::where('Activo', 1)
        ->whereDate('FechaVencimiento', '<=', now())
        ->with(['proposicion.cliente.tasaMora'])
        ->get();
    
    echo "   Créditos vencidos encontrados: " . $creditosPrueba->count() . "\n";
    foreach ($creditosPrueba as $cred) {
        echo "   - Crédito {$cred->CreditoID}: Vence {$cred->FechaVencimiento->format('Y-m-d')}\n";
        echo "     PropID: {$cred->ProposicionCreditoID}\n";
        echo "     Saldo: {$cred->proposicion?->SaldoPendiente}\n";
        echo "     TasaMora: {$cred->proposicion?->cliente?->tasaMora?->Porcentaje}%\n";
    }
}
echo "\n";

echo "====== FIN TEST ======\n";
?>
