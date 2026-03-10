<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class GastoReporteController extends Controller
{
    public function descargarExcel()
    {
        $fechaDesde = request()->query('fecha_desde');
        $fechaHasta = request()->query('fecha_hasta');

        $query = Gasto::query()
            ->activos()
            ->with('tipoComprobante', 'motivo', 'detalles')
            ->orderBy('FechaCreacion', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
            }
        }

        $gastos = $query->get();
        $totalGeneral = $gastos->sum('Total');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gastos');

        // Estilos
        $styleTitle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ];

        $styleHeader = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $styleTotal = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F0FE']],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $styleData = [
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // Título
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'REPORTE DE GASTOS');
        $sheet->getStyle('A1')->applyFromArray($styleTitle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Información de filtros
        $row = 2;
        $fechaReporte = now()->format('d/m/Y H:i');
        $sheet->setCellValue('A' . $row, 'Fecha de Reporte: ' . $fechaReporte);
        $row++;

        if ($fechaDesde && $fechaHasta) {
            $sheet->setCellValue('A' . $row, 'Período: ' . $fechaDesde . ' al ' . $fechaHasta);
        } elseif ($fechaDesde) {
            $sheet->setCellValue('A' . $row, 'Desde: ' . $fechaDesde);
        } elseif ($fechaHasta) {
            $sheet->setCellValue('A' . $row, 'Hasta: ' . $fechaHasta);
        }
        $row += 2;

        // Encabezados
        $headers = ['Fecha', 'Tipo Comprobante', 'Número', 'Proveedor', 'Motivo', 'Método Gasto', 'Descripción', 'Monto', 'Observaciones'];
        foreach ($headers as $col => $header) {
            $colLetter = chr(65 + $col);
            $sheet->setCellValue($colLetter . $row, $header);
            $sheet->getStyle($colLetter . $row)->applyFromArray($styleHeader);
        }

        $row++;

        // Datos - una fila por cada detalle
        foreach ($gastos as $gasto) {
            $detalles = $gasto->detalles;
            $isFirst = true;

            if ($detalles->isEmpty()) {
                // Gasto sin detalles (datos antiguos)
                $sheet->setCellValue('A' . $row, $gasto->FechaCreacion->format('d/m/Y'));
                $sheet->setCellValue('B' . $row, $gasto->tipoComprobante->Nombre);
                $sheet->setCellValue('C' . $row, $gasto->Numero);
                $sheet->setCellValue('D' . $row, $gasto->NombreProveedor);
                $sheet->setCellValue('E' . $row, $gasto->motivo->Nombre);
                $sheet->setCellValue('F' . $row, $gasto->MetodoGasto);
                $sheet->setCellValue('G' . $row, $gasto->Descripcion ?? '');
                $sheet->setCellValue('H' . $row, $gasto->Total);
                $sheet->setCellValue('I' . $row, $gasto->Observaciones);

                for ($col = 0; $col < 9; $col++) {
                    $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleData);
                }
                $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
                $row++;
            } else {
                foreach ($detalles as $detalle) {
                    if ($isFirst) {
                        $sheet->setCellValue('A' . $row, $gasto->FechaCreacion->format('d/m/Y'));
                        $sheet->setCellValue('B' . $row, $gasto->tipoComprobante->Nombre);
                        $sheet->setCellValue('C' . $row, $gasto->Numero);
                        $sheet->setCellValue('D' . $row, $gasto->NombreProveedor);
                        $sheet->setCellValue('E' . $row, $gasto->motivo->Nombre);
                        $sheet->setCellValue('F' . $row, $gasto->MetodoGasto);
                        $sheet->setCellValue('I' . $row, $gasto->Observaciones);
                        $isFirst = false;
                    }

                    $sheet->setCellValue('G' . $row, $detalle->Descripcion);
                    $sheet->setCellValue('H' . $row, $detalle->Monto);

                    for ($col = 0; $col < 9; $col++) {
                        $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleData);
                    }
                    $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
                    $row++;
                }
            }
        }

        // Total
        $sheet->setCellValue('G' . $row, 'TOTAL:');
        $sheet->setCellValue('H' . $row, $totalGeneral);
        $sheet->getStyle('G' . $row . ':H' . $row)->applyFromArray($styleTotal);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(20);

        // Descargar
        $writer = new Xlsx($spreadsheet);
        $fileName = storage_path('temp/Reporte_Gastos_' . now()->format('Y-m-d_His') . '.xlsx');

        if (!is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        $writer->save($fileName);

        return response()->download($fileName, 'Reporte_Gastos_' . now()->format('Y-m-d_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function descargarPdf()
    {
        $fechaDesde = request()->query('fecha_desde');
        $fechaHasta = request()->query('fecha_hasta');

        $query = Gasto::query()
            ->activos()
            ->with('tipoComprobante', 'motivo', 'detalles')
            ->orderBy('FechaCreacion', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
            }
        }

        $gastos = $query->get();
        $totalGeneral = $gastos->sum('Total');

        $pdf = Pdf::loadView('reportes.gastos', [
            'gastos' => $gastos,
            'total_general' => $totalGeneral,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_reporte' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Reporte_Gastos_' . now()->format('Y-m-d_His') . '.pdf');
    }
}
