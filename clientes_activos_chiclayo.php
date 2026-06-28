<?php
/**
 * Exportar Excel de clientes con creditos activos - Sede Chiclayo
 *
 * Ejecutar:
 *   php clientes_activos_chiclayo.php
 *
 * Filtra creditos con EstatusCreditoFinal = ACTIVO y SaldoPendiente > 0
 * Solo SedeID=1 (Chiclayo)
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Credito;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

set_time_limit(0);
ini_set('memory_limit', '512M');

$SEDE_ID = 1;

echo "Generando Excel de clientes con creditos activos (Chiclayo)...\n";

$creditos = Credito::where('Credito.EstatusCreditoFinal', 'ACTIVO')
    ->where('Credito.SedeID', $SEDE_ID)
    ->where('Credito.Activo', 1)
    ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
    ->where('ProposicionCredito.SaldoPendiente', '>', 0)
    ->with([
        'proposicion' => function ($q) {
            $q->with(['cliente', 'zona', 'tipoCredito']);
        }
    ])
    ->orderBy('Credito.FechaGeneracion', 'desc')
    ->get();

echo "Encontrados: " . count($creditos) . " creditos activos con saldo > 0\n";

if ($creditos->isEmpty()) {
    echo "No hay creditos activos para exportar.\n";
    exit(0);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Créditos Activos');

$headerFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => '4472C4'],
];
$headerFont = [
    'bold' => true,
    'color' => ['rgb' => 'FFFFFF'],
    'size' => 11,
];
$headerAlign = [
    'horizontal' => Alignment::HORIZONTAL_CENTER,
    'vertical' => Alignment::VERTICAL_CENTER,
    'wrapText' => true,
];
$titleStyle = [
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => $headerFill,
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$borderStyle = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
    ],
];
$dataAlign = [
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];
$moneyFormat = '#,##0.00';
$pctFormat = '0.00%';

// Titulo
$headers = [
    'A' => 'DNI',
    'B' => 'Cliente',
    'C' => 'Domicilio',
    'D' => 'Zona',
    'E' => 'Código Crédito',
    'F' => 'Tipo Crédito',
    'G' => 'Monto Total',
    'H' => 'Saldo Pendiente',
    'I' => 'Tasa Interés',
    'J' => 'Plazo (días)',
    'K' => 'N° Cuotas',
    'L' => 'Monto Cuota',
    'M' => 'F. Inicio',
    'N' => 'F. Vencimiento',
];

$lastColLetter = chr(65 + count($headers) - 1);

$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->setCellValue('A1', 'CLIENTES CON CRÉDITOS ACTIVOS - CHICLAYO');
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

$row = 2;
foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . $row, $label);
    $sheet->getStyle($col . $row)->applyFromArray([
        'font' => $headerFont,
        'fill' => $headerFill,
        'alignment' => $headerAlign,
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
}
$sheet->getRowDimension(2)->setRowHeight(22);

$row = 3;
$totalMonto = 0;
$totalSaldo = 0;

foreach ($creditos as $credito) {
    $prop = $credito->proposicion;
    if (!$prop) continue;
    $cliente = $prop->cliente;

    $monto  = (float) $prop->MontoTotal;
    $saldo  = (float) $prop->SaldoPendiente;
    $tasa   = (float) $prop->TasaInteres / 100;
    $plazo  = (int) $prop->Plazo;
    $cuotas = (int) $prop->NumeroCuotas;
    $montoCuota = (float) $prop->MontoCuota;

    $fechaInicio     = $credito->FechaInicio ?? '';
    $fechaVencimiento = $credito->FechaVencimiento ?? '';

    $data = [
        'A' => $cliente->DNI ?? '',
        'B' => $cliente->NombresApellidos ?? '',
        'C' => $cliente->Domicilio ?? '',
        'D' => $prop->zona ? $prop->zona->Nombre : '',
        'E' => $prop->CodigoCredito ?? '',
        'F' => $prop->tipoCredito ? $prop->tipoCredito->Descripcion : '',
        'G' => $monto,
        'H' => $saldo,
        'I' => $tasa,
        'J' => $plazo,
        'K' => $cuotas,
        'L' => $montoCuota,
        'M' => $fechaInicio,
        'N' => $fechaVencimiento,
    ];

    foreach ($data as $col => $val) {
        $cell = $sheet->getCell($col . $row);
        $cell->setValue($val);
        $cell->getStyle()->applyFromArray($dataAlign);
        $cell->getStyle()->applyFromArray($borderStyle);
    }

    $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
    $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
    $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode($pctFormat);
    $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode($moneyFormat);

    $totalMonto += $monto;
    $totalSaldo += $saldo;
    $row++;
}

$row--;

// Fila de totales
$totalRow = $row + 2;
$totalStyle = [
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
];

$sheet->mergeCells("A{$totalRow}:F{$totalRow}");
$sheet->setCellValue("A{$totalRow}", 'TOTAL: ' . count($creditos) . ' créditos activos');
$sheet->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray($totalStyle);

$sheet->setCellValue("G{$totalRow}", $totalMonto);
$sheet->getStyle("G{$totalRow}")->applyFromArray($totalStyle);
$sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode($moneyFormat);

$sheet->setCellValue("H{$totalRow}", $totalSaldo);
$sheet->getStyle("H{$totalRow}")->applyFromArray($totalStyle);
$sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode($moneyFormat);

for ($c = 'I'; $c <= $lastColLetter; $c++) {
    $sheet->getStyle("{$c}{$totalRow}")->applyFromArray($totalStyle);
}

// Anchos de columna
$colWidths = [
    'A' => 12, 'B' => 38, 'C' => 40, 'D' => 10, 'E' => 16, 'F' => 22,
    'G' => 16, 'H' => 16, 'I' => 14, 'J' => 14, 'K' => 12, 'L' => 14,
    'M' => 14, 'N' => 16,
];
foreach ($colWidths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

$sheet->getStyle("A3:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle("G{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Congelar panel superior
$sheet->freezePane('A3');

// Guardar
$tempDir = storage_path('temp');
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
$filename = 'clientes_activos_chiclayo_' . date('Ymd_His') . '.xlsx';
$filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "Excel generado: {$filepath}\n";
echo "  Créditos: " . count($creditos) . "\n";
echo "  Monto Total: S/ " . number_format($totalMonto, 2) . "\n";
echo "  Saldo Pendiente: S/ " . number_format($totalSaldo, 2) . "\n";
echo "Done.\n";
