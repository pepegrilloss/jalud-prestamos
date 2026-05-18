<?php

namespace App\Http\Controllers;

use App\Models\ProposicionCredito;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DescargarActaExcelController extends Controller
{
    public function descargar()
    {
        $fechaInput = request()->query('fecha');
        $fecha = $fechaInput ? \Carbon\Carbon::createFromFormat('Y-m-d', $fechaInput) : now();

        $sedeId = auth()->user()->getEffectiveSedeId();

        $proposiciones = ProposicionCredito::with(['cliente', 'zona', 'tipoCredito', 'tasa'])
            ->where('Activo', true)
            ->whereDate('FechaPropuesta', '=', $fecha)
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->orderBy('CodigoCredito')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Acta de Créditos');

        // Estilos
        $styleTitle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ];

        $styleHeader = [
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8E8E8']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $styleDataCenter = [
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $styleDataLeft = [
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center', 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $styleDataNum = [
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'numberFormat' => ['formatCode' => '#,##0.00'],
        ];

        // Título
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'ACTA DE CRÉDITOS');
        $sheet->getStyle('A1')->applyFromArray($styleTitle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Fecha
        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'Fecha: ' . $fecha->format('d/m/Y'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');
        
        $row = 4;

        // Encabezados
        $headers = [
            'A' => "CÓDIGO\nCRÉDITO",
            'B' => 'ZONA',
            'C' => "CÓDIGO\nCLIENTE",
            'D' => "NOMBRE\nCLIENTE",
            'E' => 'MONTO',
            'F' => 'TASA',
            'G' => "MONTO\nTOTAL",
            'H' => 'INTERESES',
            'I' => 'CUOTAS',
            'J' => 'DÍAS',
            'K' => "TIPO DE\nCRÉDITO",
            'L' => 'FIRMA'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->applyFromArray($styleHeader);
        }
        $sheet->getRowDimension($row)->setRowHeight(30);

        $row++;

        // Datos
        if ($proposiciones->isEmpty()) {
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->setCellValue('A' . $row, 'No hay créditos registrados');
            $sheet->getStyle('A' . $row)->applyFromArray($styleDataCenter);
            $row++;
        } else {
            foreach ($proposiciones as $proposicion) {
                $montoTotalPagar = $proposicion->MontoTotal + $proposicion->MontoInteres;

                $sheet->setCellValueExplicit('A' . $row, $proposicion->CodigoCredito, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B' . $row, $proposicion->zona->Nombre ?? 'N/A');
                $sheet->setCellValue('C' . $row, $proposicion->ClienteID);
                $sheet->setCellValue('D' . $row, $proposicion->cliente->NombresApellidos ?? 'N/A');
                $sheet->setCellValue('E' . $row, $proposicion->MontoTotal);
                $sheet->setCellValue('F' . $row, $proposicion->TasaInteres / 100);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->setCellValue('G' . $row, $montoTotalPagar);
                $sheet->setCellValue('H' . $row, $proposicion->MontoInteres);
                $sheet->setCellValue('I' . $row, $proposicion->NumeroCuotas);
                $sheet->setCellValue('J' . $row, $proposicion->Plazo);
                $sheet->setCellValue('K' . $row, $proposicion->tipoCredito->Descripcion ?? 'N/A');
                $sheet->setCellValue('L' . $row, ''); // Firma

                $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($styleDataCenter);
                $sheet->getStyle('D' . $row)->applyFromArray($styleDataLeft); // Nombre a la izquierda
                $sheet->getStyle('E' . $row)->applyFromArray($styleDataNum);
                $sheet->getStyle('F' . $row)->applyFromArray($styleDataCenter); // Tasa
                $sheet->getStyle('G' . $row . ':H' . $row)->applyFromArray($styleDataNum);
                $sheet->getStyle('I' . $row . ':J' . $row)->applyFromArray($styleDataCenter);
                $sheet->getStyle('K' . $row)->applyFromArray($styleDataCenter);
                $sheet->getStyle('L' . $row)->applyFromArray($styleDataCenter);
                
                // Set the row height slightly smaller or auto. 40 was un-centered looking.
                $sheet->getRowDimension($row)->setRowHeight(35);
                $row++;
            }
        }

        // Anchos de columna
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(40); // Más ancho para el nombre
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(20);
        $sheet->getColumnDimension('L')->setWidth(20);

        // Firmas y Observaciones
        $row += 3;
        $sheet->mergeCells('D' . $row . ':E' . $row);
        $sheet->getStyle('D' . $row . ':E' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells('H' . $row . ':I' . $row);
        $sheet->getStyle('H' . $row . ':I' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        
        $row++;
        $sheet->mergeCells('D' . $row . ':E' . $row);
        $sheet->setCellValue('D' . $row, 'JEFE DE OFICINA');
        $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal('center')->setVertical('top');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true)->setSize(11);
        
        $sheet->mergeCells('H' . $row . ':I' . $row);
        $sheet->setCellValue('H' . $row, 'ASISTENTE ADMINISTRATIVO');
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('center')->setVertical('top');
        $sheet->getStyle('H' . $row)->getFont()->setBold(true)->setSize(11);

        $row += 2;
        $sheet->setCellValue('A' . $row, 'OBSERVACIÓN:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row++;
        $sheet->mergeCells('A' . $row . ':L' . $row);
        $sheet->getStyle('A' . $row . ':L' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $row++;
        $sheet->mergeCells('A' . $row . ':L' . $row);
        $sheet->getStyle('A' . $row . ':L' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        // Descargar
        $writer = new Xlsx($spreadsheet);
        $fileName = storage_path('temp/Acta_Creditos_' . now()->format('Y-m-d_His') . '.xlsx');

        if (!is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        $writer->save($fileName);

        return response()->download($fileName, 'Acta_Creditos_' . $fecha->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
