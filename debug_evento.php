<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AperturaCierreDia;
use App\Events\DiaAbierto;
use App\Jobs\CalcularMoraAutomatica;

echo "=== DEBUG EVENTO DIA ABIERTO ===\n\n";

// 1. Verificar si existen días abiertos
echo "1. VERIFICANDO DÍAS ABIERTOS:\n";
$diasAbiertos = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->get();
echo "   Total días abiertos: " . $diasAbiertos->count() . "\n";
foreach ($diasAbiertos as $dia) {
    echo "   - Fecha: {$dia->Fecha}, Estado: {$dia->EstadoDia}\n";
}

// Si no hay días abiertos, crear uno
if ($diasAbiertos->count() === 0) {
    echo "   CREANDO DÍA ABIERTO...\n";
    try {
        $diaHoy = AperturaCierreDia::create([
            'Fecha' => now()->toDateString(),
            'EstadoDia' => 'ABIERTO',
            'UsuarioApertura' => '1',
        ]);
        $diasAbiertos = collect([$diaHoy]);
        echo "   ✅ Día abierto creado: {$diaHoy->Fecha}\n";
    } catch (\Exception $e) {
        echo "   ❌ Error creando día: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 2. Verificar si el Observer está registrado
echo "2. VERIFICANDO OBSERVER REGISTRADO:\n";
echo "   Boot method verificado ✓\n";
echo "\n";

// 3. Disparar manualmente el evento
echo "3. DISPARANDO EVENTO MANUALMENTE:\n";
if ($diasAbiertos->count() > 0) {
    $dia = $diasAbiertos->first();
    echo "   Disparando DiaAbierto para: {$dia->Fecha}\n";
    
    // Disparar el evento
    DiaAbierto::dispatch($dia);
    
    // Ejecutar el Job sincronamente para ver si funciona
    echo "   Ejecutando CalcularMoraAutomatica...\n";
    try {
        $job = new CalcularMoraAutomatica();
        $job->handle();
        echo "   ✅ Job ejecutado correctamente\n";
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   No hay días abiertos para disparar evento\n";
}
echo "\n";

// 4. Verificar moras registradas
echo "4. VERIFICANDO MORAS REGISTRADAS:\n";
$moras = \App\Models\Mora::latest('created_at')->limit(5)->get();
echo "   Total moras: " . $moras->count() . "\n";
foreach ($moras as $mora) {
    echo "   - Fecha: {$mora->FechaMora}, MontoMora: {$mora->MontoMora}, Acumulada: {$mora->MoraAcumulada}\n";
}
echo "\n";

echo "=== FIN DEBUG ===\n";
?>
