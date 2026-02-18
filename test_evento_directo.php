<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\DiaAbierto;
use App\Models\AperturaCierreDia;
use Illuminate\Support\Facades\Event;

echo "====== TEST DIRECTO DE EVENTO ======\n\n";

// 1. Escuchar el evento manualmente para confirmar que se dispara
echo "1. REGISTRANDO LISTENER MANUAL...\n";
Event::listen(DiaAbierto::class, function ($event) {
    echo "   ✓ EVENTO DISPARADO EN LISTENER MANUAL\n";
    echo "   Fecha: {$event->aperturaCierre->Fecha}\n";
});

// 2. Obtener o eliminar un día abierto anterior
echo "\n2. LIMPIANDO DÍA ANTERIOR...\n";
$diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();
if ($diaAbierto) {
    $diaAbierto->delete(); // Eliminarlo, no solo cerrarlo
    echo "   ✓ Día abierto eliminado\n";
}

// 3. Crear un nuevo día ABIERTO
echo "\n3. CREANDO NÚ EVAIRTADO...\n";
$dia = AperturaCierreDia::create([
    'Fecha' => '2026-02-21',
    'EstadoDia' => 'ABIERTO',
    'FechaApertura' => now(),
    'UsuarioAperturaID' => 1,
]);
echo "   ✓ Día creado: ID {$dia->AperturaCierreDiaID}\n";

echo "\n4. VERIFICANDO ESTADO...\n";
echo "   Estado actual: {$dia->EstadoDia}\n";

echo "\n====== FIN TEST ======\n";
?>
