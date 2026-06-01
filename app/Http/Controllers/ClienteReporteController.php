<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ClienteReporteController extends Controller
{
    private function query()
    {
        $fechaDesde = request()->query('fecha_desde');
        $fechaHasta = request()->query('fecha_hasta');
        $sedeId = auth()->user()->getEffectiveSedeId();

        $query = Cliente::query()
            ->where('Activo', true)
            ->with([
                'negocio' => fn($q) => $q->with(['ciudad', 'zona', 'giro', 'telefonos']),
                'sede',
            ])
            ->when($sedeId, fn($q) => $q->where('SedeID', $sedeId))
            ->orderBy('NombresApellidos', 'asc');

        if (!empty($fechaDesde) && $fechaDesde !== 'null') {
            try {
                $query->whereDate('FechaRegistro', '>=', Carbon::parse($fechaDesde)->toDateString());
            } catch (\Exception) {}
        }

        if (!empty($fechaHasta) && $fechaHasta !== 'null') {
            try {
                $query->whereDate('FechaRegistro', '<=', Carbon::parse($fechaHasta)->toDateString());
            } catch (\Exception) {}
        }

        return [$query->get(), $fechaDesde, $fechaHasta];
    }

    public function descargarExcel()
    {
        [$clientes, $fechaDesde, $fechaHasta] = $this->query();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes');

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

        $colCount = 9;
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'REPORTE DE CLIENTES');
        $sheet->getStyle('A1')->applyFromArray($styleTitle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $row = 2;
        $sheet->setCellValue('A' . $row, 'Fecha de Reporte: ' . now()->format('d/m/Y H:i'));
        $row++;

        if ($fechaDesde && $fechaHasta) {
            $sheet->setCellValue('A' . $row, 'Período: ' . $fechaDesde . ' al ' . $fechaHasta);
        } elseif ($fechaDesde) {
            $sheet->setCellValue('A' . $row, 'Desde: ' . $fechaDesde);
        } elseif ($fechaHasta) {
            $sheet->setCellValue('A' . $row, 'Hasta: ' . $fechaHasta);
        }
        $row += 2;

        $headers = ['DNI', 'Apellidos y Nombres', 'Sexo', 'Domicilio', 'Ciudad', 'Zona', 'Dirección Negocio', 'Giro', 'Teléfonos'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . $row, $header);
            $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleHeader);
        }
        $row++;

        foreach ($clientes as $cliente) {
            $negocio = $cliente->negocio;
            $telefonos = $negocio?->telefonos?->pluck('Telefono')?->implode(', ') ?? '-';

            $sheet->setCellValue('A' . $row, $cliente->DNI);
            $sheet->setCellValue('B' . $row, $cliente->NombresApellidos);
            $sheet->setCellValue('C' . $row, $cliente->Sexo === 'M' ? 'Masculino' : ($cliente->Sexo === 'F' ? 'Femenino' : ($cliente->Sexo ?? '-')));
            $sheet->setCellValue('D' . $row, $cliente->Domicilio ?? '-');
            $sheet->setCellValue('E' . $row, $negocio?->ciudad?->Nombre ?? '-');
            $sheet->setCellValue('F' . $row, $negocio?->zona?->Nombre ?? '-');
            $sheet->setCellValue('G' . $row, $negocio?->DireccionNegocio ?? '-');
            $sheet->setCellValue('H' . $row, $negocio?->giro?->Descripcion ?? '-');
            $sheet->setCellValue('I' . $row, $telefonos);

            for ($col = 0; $col < $colCount; $col++) {
                $sheet->getStyle(chr(65 + $col) . $row)->applyFromArray($styleData);
            }
            $row++;
        }

        $sheet->setCellValue('H' . $row, 'TOTAL CLIENTES:');
        $sheet->setCellValue('I' . $row, $clientes->count());
        $sheet->getStyle('H' . $row . ':I' . $row)->applyFromArray($styleTotal);
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal('right');

        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(28);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(22);

        $writer = new Xlsx($spreadsheet);
        $fileName = storage_path('temp/Reporte_Clientes_' . now()->format('Y-m-d_His') . '.xlsx');

        if (!is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        $writer->save($fileName);

        return response()->download($fileName, 'Reporte_Clientes_' . now()->format('d-m-Y') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function descargarPdf()
    {
        [$clientes, $fechaDesde, $fechaHasta] = $this->query();

        $pdf = Pdf::loadView('reportes.clientes', [
            'clientes' => $clientes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_reporte' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('Reporte_Clientes_' . now()->format('Y-m-d_His') . '.pdf');
    }
}
