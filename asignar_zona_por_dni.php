<?php
/**
 * Asignar zona por DNI a la ultima proposicion activa con saldo
 * Chiclayo (SedeID=1)
 *
 * Ejecutar: php asignar_zona_por_dni.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$SEDE_ID = 1;

$zonas = [
    1 => [ // CHICLAYO 01
        '41926014', '16759385', '16430117', '16449882', '16641502',
        '16488192', '16735896', '80441718', '43932790', '78006116',
        '78010919', '16716124', '77662906', '176099386', '17450021',
        '16770610',
    ],
    2 => [ // CHICLAYO 02
        '16753833', '43988123', '77037219', '16633104', '47443380',
        '44172023', '16494549', '16476860', '16629828',
    ],
    3 => [ // CHICLAYO 03
        '16729095', '16625605', '43044643', '16678436', '27291567',
        '16736840', '71335268', '16692490', '16796925', '42444574',
        '16758118', '74952739', '16715748', '46918152', '46802110',
        '17609324', '16790404', '42609310', '00861228', '16652257',
        '72544874', '77019616', '40997676',
    ],
];

$zonasNombres = [1 => 'CHICLAYO 01', 2 => 'CHICLAYO 02', 3 => 'CHICLAYO 03'];

echo "=== ASIGNAR ZONA POR DNI (CHICLAYO) ===\n\n";

$totalActualizados = 0;
$totalCorrectos = 0;
$totalNoEncontrados = 0;
$totalSinCredito = 0;

foreach ($zonas as $zonaID => $dnis) {
    foreach ($dnis as $dni) {
        $cliente = DB::table('Cliente')
            ->where('SedeID', $SEDE_ID)
            ->where('DNI', $dni)
            ->where('Activo', 1)
            ->first();

        if (!$cliente) {
            echo "  [??] DNI {$dni} -> CLIENTE NO ENCONTRADO\n";
            $totalNoEncontrados++;
            continue;
        }

        $ultimaProp = DB::table('ProposicionCredito')
            ->where('ClienteID', $cliente->ClienteID)
            ->where('SedeID', $SEDE_ID)
            ->where('Activo', 1)
            ->where('SaldoPendiente', '>', 0)
            ->orderByDesc('ProposicionCreditoID')
            ->first();

        if (!$ultimaProp) {
            echo "  [--] DNI {$dni} ({$cliente->NombresApellidos}) -> Sin credito activo con saldo\n";
            $totalSinCredito++;
            continue;
        }

        $zonaActual = $ultimaProp->ZonaID ? ($zonasNombres[$ultimaProp->ZonaID] ?? 'Zona'.$ultimaProp->ZonaID) : 'SIN ZONA';

        if ($ultimaProp->ZonaID == $zonaID) {
            echo "  [OK] DNI {$dni} ({$cliente->NombresApellidos}) -> Ya en {$zonasNombres[$zonaID]} ({$ultimaProp->CodigoCredito})\n";
            $totalCorrectos++;
        } else {
            DB::table('ProposicionCredito')
                ->where('ProposicionCreditoID', $ultimaProp->ProposicionCreditoID)
                ->update(['ZonaID' => $zonaID]);

            echo "  [CORR] DNI {$dni} ({$cliente->NombresApellidos}): {$zonaActual} -> {$zonasNombres[$zonaID]} ({$ultimaProp->CodigoCredito})\n";
            $totalActualizados++;
        }
    }
}

echo "\n=== RESUMEN ===\n";
echo "  Corregidos:      {$totalActualizados}\n";
echo "  Ya correctos:    {$totalCorrectos}\n";
echo "  No encontrados:  {$totalNoEncontrados}\n";
echo "  Sin credito:     {$totalSinCredito}\n";
echo "\nDone.\n";
