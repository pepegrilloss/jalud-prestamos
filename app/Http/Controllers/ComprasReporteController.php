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
        $sedeId = auth()->user()->getEffectiveSedeId();

        $query = Compra::query()
            ->activos()
            ->with('tipoComprobante', 'proveedor', 'detalles')
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->orderBy('FechaEmision', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaEmision', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaEmision', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
            }
        }

        $compras = $query->get();
        $totalGeneral = $compras->sum('Total');
        $totalIgv = $compras->sum('MontoIGV');

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
        $sheet->mergeCells('A1:J1');
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
        $headers = ['Fecha Emisión', 'Tipo Comprobante', 'Serie / Número', 'Proveedor', 'Productos', 'Ítems', 'Total', 'IGV', 'Tipo', 'Pago'];
        $colCount = count($headers);
        foreach ($headers as $col => $header) {
            $colLetter = chr(65 + $col);
            $sheet->setCellValue($colLetter . $row, $header);
            $sheet->getStyle($colLetter . $row)->applyFromArray($styleHeader);
        }

        $row++;

        // Datos
        foreach ($compras as $compra) {
            $detalles = $compra->detalles;
            $itemsCount = $detalles->count();

            if ($detalles->isEmpty()) {
                $sheet->setCellValue('A' . $row, $compra->FechaEmision->format('d/m/Y'));
                $sheet->setCellValue('B' . $row, $compra->tipoComprobante->Nombre);
                $sheet->setCellValue('C' . $row, $compra->Numero);
                $sheet->setCellValue('D' . $row, $compra->proveedor?->Nombre);
                $sheet->setCellValue('E' . $row, $compra->ProductoServicio ?? '-');
                $sheet->setCellValue('F' . $row, 1);
                $sheet->setCellValue('G' . $row, $compra->Total);
                $sheet->setCellValue('H' . $row, $compra->MontoIGV ?? 0);
                $sheet->setCellValue('I' . $row, $compra->TipoCompra);
                $sheet->setCellValue('J' . $row, $compra->EstadoPago);

                for ($col = 0; $col < $colCount; $col++) {
                    $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleData);
                }
                $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
                $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
                $row++;
            } else {
                $productos = $detalles->pluck('ProductoServicio')->implode(', ');
                $sheet->setCellValue('A' . $row, $compra->FechaEmision->format('d/m/Y'));
                $sheet->setCellValue('B' . $row, $compra->tipoComprobante->Nombre);
                $sheet->setCellValue('C' . $row, $compra->Numero);
                $sheet->setCellValue('D' . $row, $compra->proveedor?->Nombre);
                $sheet->setCellValue('E' . $row, $productos);
                $sheet->setCellValue('F' . $row, $itemsCount);
                $sheet->setCellValue('G' . $row, $compra->Total);
                $sheet->setCellValue('H' . $row, $compra->MontoIGV ?? 0);
                $sheet->setCellValue('I' . $row, $compra->TipoCompra);
                $sheet->setCellValue('J' . $row, $compra->EstadoPago);

                for ($col = 0; $col < $colCount; $col++) {
                    $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleData);
                }
                $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
                $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');
                $row++;
            }
        }

        // Total
        $sheet->setCellValue('E' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('F' . $row, $compras->sum(fn($c) => $c->detalles->count()));
        $sheet->setCellValue('G' . $row, $totalGeneral);
        $sheet->setCellValue('H' . $row, $totalIgv);
        $sheet->getStyle('E' . $row . ':H' . $row)->applyFromArray($styleTotal);
        $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('right');

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(10);

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
        $sedeId = auth()->user()->getEffectiveSedeId();

        $query = Compra::query()
            ->activos()
            ->with('tipoComprobante', 'proveedor', 'detalles')
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->orderBy('FechaEmision', 'desc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaEmision', '>=', \Carbon\Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception $e) {
            }
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaEmision', '<=', \Carbon\Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception $e) {
            }
        }

        $compras = $query->get();
        $totalGeneral = $compras->sum('Total');
        $totalIgv = $compras->sum('MontoIGV');

        $pdf = Pdf::loadView('reportes.compras', [
            'compras' => $compras,
            'total_general' => $totalGeneral,
            'total_igv' => $totalIgv,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_reporte' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Reporte_Compras_' . now()->format('Y-m-d_His') . '.pdf');
    }
}
