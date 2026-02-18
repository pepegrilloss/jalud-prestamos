<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AperturaCierreDia;
use Carbon\Carbon;

echo "====== TEST VALIDACIÓN FECHA FUTURA ======\n\n";

echo "1. INTENTANDO CREAR DÍA CON FECHA FUTURA...\n";
try {
    $fechaFutura = now()->addDays(5)->toDateString(); // 5 días en el futuro
    echo "   Intentando crear día para: {$fechaFutura}\n";
    
    $dia = AperturaCierreDia::create([
        'Fecha' => $fechaFutura,
        'EstadoDia' => 'ABIERTO',
        'FechaApertura' => now(),
        'UsuarioAperturaID' => 1,
    ]);
    
    echo "   ❌ ERROR: Se permitió crear día con fecha futura!\n";
} catch (\Exception $e) {
    echo "   ✓ Validación funcionando: {$e->getMessage()}\n";
}

echo "\n2. INTENTANDO CREAR DÍA CON FECHA DE HOY...\n";
try {
    // Primero cerrar cualquier día abierto
    AperturaCierreDia::where('EstadoDia', 'ABIERTO')->update(['EstadoDia' => 'CERRADO', 'FechaCierre' => now()]);
    
    // Eliminar si existe el de hoy
    $fechaHoy = today()->toDateString();
    AperturaCierreDia::whereDate('Fecha', $fechaHoy)->delete();
    
    echo "   Creando día para: {$fechaHoy}\n";
    
    $dia = AperturaCierreDia::create([
        'Fecha' => $fechaHoy,
        'EstadoDia' => 'ABIERTO',
        'FechaApertura' => now(),
        'UsuarioAperturaID' => 1,
    ]);
    
    echo "   ✓ Día creado exitosamente: ID {$dia->AperturaCierreDiaID}\n";
} catch (\Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

echo "\n3. INTENTANDO CREAR DÍA CON FECHA PASADA...\n";
try {
    // Cerrar el día de hoy primero
    $diaHoy = AperturaCierreDia::whereDate('Fecha', today())->first();
    if ($diaHoy && $diaHoy->EstadoDia === 'ABIERTO') {
        $diaHoy->update(['EstadoDia' => 'CERRADO', 'FechaCierre' => now()]);
    }
    
    $fechaPasada = now()->subDays(10)->toDateString(); // 10 días en el pasado
    echo "   Intentando crear día para: {$fechaPasada}\n";
    
    $dia = AperturaCierreDia::create([
        'Fecha' => $fechaPasada,
        'EstadoDia' => 'CERRADO', // Cerrado, no abierto
        'FechaCierre' => now(),
        'UsuarioAperturaID' => 1,
    ]);
    
    echo "   ✓ Día con fecha pasada creado: ID {$dia->AperturaCierreDiaID}\n";
} catch (\Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

echo "\n====== FIN TEST ======\n";
?>
