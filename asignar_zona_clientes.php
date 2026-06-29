<?php
/**
 * Asignar zona a ultima proposicion de credito de clientes especificos
 * Chiclayo (SedeID=1)
 *
 * Ejecutar: php asignar_zona_clientes.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$SEDE_ID = 1;

$zonas = [
    1 => [ // CHICLAYO 01
        'CHINININ DIAZ DIANA ISABEL',
        'ALEXANDER LAGUNAS FERNANDEZ',
        'MENDOZA DE MONTALVO ROSA IMELDA',
        'PORRAS CUEVA EULALIA GLADYS',
        'SALAZAR DE CAMIZAN MARIA',
        'MEL DE CHAFLOQUE CATALINA',
        'CERVERA LLONTOP YVONNE ELIZABETH',
        'CHAFLOQUE TULLUME SANTOS',
        'PARIACURI YAIPEN JESUS HIPOLITO',
        'ROMAN VASQUEZ EDWARD',
        'CALVAY CHAFLOQUE FRANSY',
        'CORTEZ CHERRES CHARBELL DE LOS SANTOS',
        'GARCIA CHANAME DANIEL',
        'CENTURION HERNANDEZ ELIZABETH',
        'VILLANUEVA MENDOZA SANDRA',
        'SANCHEZ CHUNGA VIRGINIA',
    ],
    2 => [ // CHICLAYO 02
        'ALARCON BULLON MERCEDES',
        'AYALA CHAVEZ YOVANY',
        'MONJA IRIGOIN ANGIE',
        'VEGA SORIANO LUZ AMELIA',
        'NULEZ JULON MARI ESTHER',
        'SAMILLAN RECALDE SLVIA',
        'RENTERIA TIGRE ALEJANDRINA',
        'SANTISTEBAN SANTISTEBAN ANTONIO',
        'CONDOR BERNAL MAYDA',
        'GUEVARA CABRERA LILI',
        'CIEZA ZARATE DIONA',
    ],
    3 => [ // CHICLAYO 03
        'TORRES GUEVARA IMELDA',
        'DAVILA BUSTAMANTE LUZMILA',
        'RODAS VARGAS PAOLA',
        'RODRIGUEZ SANCHEZ DELCY',
        'MUÑOZ FERNANDEZ ABSALON',
        'CAICEDO GONZALES MARIA',
        'DELGADO CRUUZ HISELA',
        'PEREZ BENEL GILBERTO',
        'BRAVO MONTENEGRO CLAUDIA',
        'GUARNIZO PEÑA JUANA',
        'VASQUEZ TARRILLO HERMITANEO',
        'MONTALVAN YNOÑAN SUSANA',
        'NINAQUISPE CARHUATANTA NELY',
        'QUISPE PORTOCARRERON JUNIOR',
        'RIVERA INOÑAN ANA',
        'ANAYA ANAYA JOSE LUIS',
        'SUCLUPE LEYVA MILAGROS',
        'CHAVEZ CHAVEZ MARISOL',
        'SANCHEZ HOYOS FLORMIRA',
        'CHUPEN MESTANZA GLADYS',
        'SIADEN HERNANDEZ MANUEL',
        'TANTALEAN BALAREZO ANDREA',
    ],
];

$zonasNombres = [1 => 'CHICLAYO 01', 2 => 'CHICLAYO 02', 3 => 'CHICLAYO 03'];

echo "=== ASIGNAR ZONA A ULTIMA PROPOSICION (CHICLAYO) ===\n\n";

// PASO 1: Buscar cada cliente y mostrar lo encontrado
echo "--- BUSQUEDA ---\n";
$asignaciones = [];

foreach ($zonas as $zonaID => $nombres) {
    foreach ($nombres as $nombreCliente) {
        $cliente = DB::table('Cliente')
            ->where('SedeID', $SEDE_ID)
            ->where('NombresApellidos', 'like', '%' . substr($nombreCliente, 0, 5) . '%')
            ->where('NombresApellidos', 'like', '%' . substr($nombreCliente, -5) . '%')
            ->where('Activo', 1)
            ->first();

        if (!$cliente) {
            echo "  [??] '{$nombreCliente}' -> NO ENCONTRADO\n";
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
            echo "  [--] '{$nombreCliente}' -> Sin credito activo con saldo\n";
            continue;
        }

        $zonaActual = $ultimaProp->ZonaID ? ($zonasNombres[$ultimaProp->ZonaID] ?? 'Zona'.$ultimaProp->ZonaID) : 'SIN ZONA';

        if ($ultimaProp->ZonaID == $zonaID) {
            echo "  [OK] '{$cliente->NombresApellidos}' -> Ya esta en {$zonasNombres[$zonaID]} ({$ultimaProp->CodigoCredito})\n";
        } else {
            echo "  [>]  '{$cliente->NombresApellidos}' -> {$zonaActual} => {$zonasNombres[$zonaID]} ({$ultimaProp->CodigoCredito})\n";
            $asignaciones[] = [
                'propID' => $ultimaProp->ProposicionCreditoID,
                'codigo' => $ultimaProp->CodigoCredito,
                'zonaID' => $zonaID,
                'zonaNombre' => $zonasNombres[$zonaID],
                'cliente' => $cliente->NombresApellidos,
            ];
        }
    }
}

// PASO 2: Ejecutar cambios
echo "\n--- EJECUTANDO CAMBIOS ---\n";
$total = 0;

foreach ($asignaciones as $a) {
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $a['propID'])
        ->update(['ZonaID' => $a['zonaID']]);
    echo "  [CORR] {$a['codigo']} ({$a['cliente']}) -> {$a['zonaNombre']}\n";
    $total++;
}

echo "\n=== RESUMEN ===\n";
echo "  Clientes no encontrados: Mostrados como [??]\n";
echo "  Zonas corregidas: {$total}\n";
echo "\nDone.\n";
