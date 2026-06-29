<?php
/**
 * Actualizar ZonaID en ProposicionCredito desde Excel de creditos activos
 *
 * Ejecutar en el servidor:
 *   php actualizar_zonas_desde_excel.php
 *
 * Asigna zona si no tiene Y corrige si tiene zona equivocada.
 * Solo creditos activos con SaldoPendiente > 0.
 * Solo SedeID=1 (Chiclayo).
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

$EXCEL_FILE = storage_path('temp/clientes_activos_chiclayo_20260628_093046.xlsx');
$SEDE_ID = 1;

set_time_limit(0);
ini_set('memory_limit', '512M');

echo "=== ACTUALIZAR ZONAS DESDE EXCEL ===\n";
echo "Archivo: {$EXCEL_FILE}\n\n";

if (!file_exists($EXCEL_FILE)) {
    echo "ERROR: No se encontro el archivo Excel.\n";
    exit(1);
}

$spreadsheet = IOFactory::load($EXCEL_FILE);
$sheet = $spreadsheet->getActiveSheet();
$totalRows = $sheet->getHighestRow();
echo "Filas totales en Excel: {$totalRows}\n";

// Cargar zonas
$zonas = DB::table('Zona')->where('SedeID', $SEDE_ID)->where('Activo', 1)->pluck('ZonaID', 'Nombre')->toArray();
echo "Zonas disponibles: " . count($zonas) . "\n";
foreach ($zonas as $nombre => $id) {
    echo "  {$nombre} => ZonaID={$id}\n";
}

$actualizados = 0;
$corregidos = 0;
$yaCorrectos = 0;
$noEncontrados = 0;
$sinZonaEnExcel = 0;
$zonaNoExiste = 0;

DB::beginTransaction();

for ($r = 3; $r <= $totalRows; $r++) {
    $codigoCredito = trim((string)($sheet->getCell('E' . $r)->getValue() ?? ''));
    $zonaNombre    = trim((string)($sheet->getCell('D' . $r)->getValue() ?? ''));
    $saldo         = (float)($sheet->getCell('H' . $r)->getValue() ?? 0);
    $monto         = (float)($sheet->getCell('G' . $r)->getValue() ?? 0);

    if (empty($codigoCredito)) continue;

    if (empty($zonaNombre)) {
        $sinZonaEnExcel++;
        continue;
    }

    // Buscar ZonaID por nombre
    $zonaID = $zonas[$zonaNombre] ?? null;
    if (!$zonaID) {
        $zonaNoExiste++;
        echo "  [!] Zona no encontrada: '{$zonaNombre}' para codigo {$codigoCredito}\n";
        continue;
    }

    // Buscar ProposicionCredito activa con saldo > 0
    $prop = DB::table('ProposicionCredito')
        ->where('CodigoCredito', $codigoCredito)
        ->where('SedeID', $SEDE_ID)
        ->where('Activo', 1)
        ->where('SaldoPendiente', '>', 0)
        ->first();

    if (!$prop) {
        $noEncontrados++;
        continue;
    }

    // Si ya tiene la zona correcta, omitir
    if (!empty($prop->ZonaID) && $prop->ZonaID == $zonaID) {
        $yaCorrectos++;
        continue;
    }

    // Actualizar o corregir
    $zonaAnterior = $prop->ZonaID;
    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->update(['ZonaID' => $zonaID]);

    if (empty($zonaAnterior)) {
        $actualizados++;
        echo "  [NUEVO] {$codigoCredito} -> ZonaID={$zonaID} ({$zonaNombre})\n";
    } else {
        $corregidos++;
        $nombreAnt = array_search($zonaAnterior, $zonas);
        echo "  [CORR]  {$codigoCredito}: {$nombreAnt} -> {$zonaNombre} | Saldo: S/{$saldo}\n";
    }
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Zonas nuevas asignadas:  {$actualizados}\n";
echo "  Zonas corregidas:        {$corregidos}\n";
echo "  Ya estaban correctas:    {$yaCorrectos}\n";
echo "  Credito no encontrado:   {$noEncontrados}\n";
echo "  Sin zona en Excel:       {$sinZonaEnExcel}\n";
echo "  Zona no existe en BD:    {$zonaNoExiste}\n";
echo "\nDone.\n";
