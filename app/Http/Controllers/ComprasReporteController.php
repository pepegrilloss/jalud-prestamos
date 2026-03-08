<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ComprasReporteController extends Controller
{
    public function descargarExcel()
    {
        $fechaDesde = request()->query('fecha_desde');
        $fechaHasta = request()->query('fecha_hasta');
        
        $query = Compra::query()
            ->activos()
            ->with('tipoComprobante')
            ->orderBy('FechaCreacion', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
                // Ignorar si falla el parsing
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
                // Ignorar si falla el parsing
            }
        }

        $compras = $query->get();
        $totalGeneral = $compras->sum('Total');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Compras');

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
        $sheet->setCellValue('A1', 'REPORTE DE COMPRAS');
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
        $headers = ['Fecha', 'Tipo Comprobante', 'Serie', 'Número', 'Proveedor', 'Producto/Servicio', 'Cantidad', 'Precio Unitario', 'Total'];
        foreach ($headers as $col => $header) {
            $colLetter = chr(65 + $col);
            $sheet->setCellValue($colLetter . $row, $header);
            $sheet->getStyle($colLetter . $row)->applyFromArray($styleHeader);
        }

        $row++;
        $startRow = $row;

        // Datos
        foreach ($compras as $compra) {
            $sheet->setCellValue('A' . $row, $compra->FechaCreacion->format('d/m/Y'));
            $sheet->setCellValue('B' . $row, $compra->tipoComprobante->Nombre);
            $sheet->setCellValue('C' . $row, $compra->Serie);
            $sheet->setCellValue('D' . $row, $compra->Numero);
            $sheet->setCellValue('E' . $row, $compra->NombreProveedor);
            $sheet->setCellValue('F' . $row, $compra->ProductoServicio);
            $sheet->setCellValue('G' . $row, $compra->Cantidad);
            $sheet->setCellValue('H' . $row, $compra->PrecioUnitario);
            $sheet->setCellValue('I' . $row, $compra->Total);

            // Aplicar estilos de datos
            for ($col = 0; $col < 9; $col++) {
                $colLetter = chr(65 + $col);
                $sheet->getStyle($colLetter . $row)->applyFromArray($styleData);
            }

            // Alineación numérica
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal('right');

            $row++;
        }

        // Total
        $sheet->setCellValue('H' . $row, 'TOTAL:');
        $sheet->setCellValue('I' . $row, $totalGeneral);
        $sheet->getStyle('H' . $row . ':I' . $row)->applyFromArray($styleTotal);
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal('right');

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(25);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);

        // Descargar
        $writer = new Xlsx($spreadsheet);
        $fileName = storage_path('temp/Reporte_Compras_' . now()->format('Y-m-d_His') . '.xlsx');
        
        if (!is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        $writer->save($fileName);

        return response()->download($fileName, 'Reporte_Compras_' . now()->format('Y-m-d_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function descargarPdf()
    {
        $fechaDesde = request()->query('fecha_desde');
        $fechaHasta = request()->query('fecha_hasta');
        
        $query = Compra::query()
            ->activos()
            ->with('tipoComprobante')
            ->orderBy('FechaCreacion', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
                // Ignorar si falla el parsing
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaCreacion', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
                // Ignorar si falla el parsing
            }
        }

        $compras = $query->get();
        $totalGeneral = $compras->sum('Total');

        $pdf = Pdf::loadView('reportes.compras', [
            'compras' => $compras,
            'total_general' => $totalGeneral,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_reporte' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('Reporte_Compras_' . now()->format('Y-m-d_His') . '.pdf');
    }
}
