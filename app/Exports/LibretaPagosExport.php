<?php

namespace App\Exports;

use App\Models\Credito;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LibretaPagosExport
{
    private Credito $credito;

    public function __construct(Credito $credito)
    {
        $this->credito = $credito;
    }

    public function generarExcel()
    {
        $credito = $this->credito;
        $proposicion = $credito->proposicion;
        $cliente = $proposicion->cliente;
        $zona = $proposicion->zona->Nombre ?? 'N/A';

        // Obtener las cuotas reales de la BD
        $cuotas = $credito->cuotas()
            ->orderBy('FechaVencimiento')
            ->get();

        if ($cuotas->isEmpty()) {
            throw new \Exception('No hay cuotas generadas para este crédito');
        }

        $fechaInicio = Carbon::parse($credito->FechaGeneracion);
        $numeroCuotas = $cuotas->count();
        $montoCuota = $proposicion->MontoCuota;
        $montoTotal = $proposicion->MontoTotal;
        $plazo = $proposicion->Plazo;
        $totalInteres = $proposicion->MontoInteres ?? 0;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Control de Pagos');

        // --- DEFINICIÓN DE ESTILOS ---
        $styleVerdeBold = [
            'font' => ['bold' => true, 'color' => ['rgb' => '008000'], 'size' => 11],
        ];
        $styleBordeVerde = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '008000']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $styleHeaderTable = [
            'font' => ['bold' => true, 'color' => ['rgb' => '008000'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '008000']]],
        ];

        // --- 1. TÍTULO "CONTROL DE PAGOS" ---
        $sheet->mergeCells('B8:E9');
        $sheet->setCellValue('B8', 'CONTROL DE PAGOS');
        $sheet->getStyle('B8')->getFont()
            ->setName('ADLaM Display')
            ->setBold(true)
            ->setSize(18)
            ->setColor(new Color('00B050'));
        $sheet->getStyle('B8')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // --- 2. NOMBRE DEL CLIENTE ---
        $sheet->mergeCells('A10:F10');
        $sheet->setCellValue('A10', $cliente->NombresApellidos);
        $sheet->getStyle('A10')->applyFromArray($styleVerdeBold);
        $sheet->getStyle('A10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- 3. FECHA FE ---
        $sheet->mergeCells('A11:B11');
        $sheet->setCellValue('A11', 'FE: ' . $fechaInicio->format('d/m/Y'));
        $sheet->getStyle('A11')->applyFromArray($styleVerdeBold);

        // --- 4. FECHA FV ---
        $sheet->mergeCells('A12:B12');
        $ultimaCuota = $cuotas->last();
        $fechaFV = Carbon::parse($ultimaCuota->FechaVencimiento);
        $sheet->setCellValue('A12', 'FV: ' . $fechaFV->format('d/m/Y'));
        $sheet->getStyle('A12')->applyFromArray($styleVerdeBold);

        // --- 5. BLOQUE DE DATOS DE CRÉDITO ---
        $sheet->setCellValue('E11', 'PRINCIPAL');
        $sheet->setCellValue('E12', 'MONTO');
        $sheet->setCellValue('F12', number_format($montoTotal + $totalInteres, 2));
        $sheet->setCellValue('E13', 'CUOTA');
        $sheet->setCellValue('F13', number_format($montoCuota, 2));
        $sheet->setCellValue('E14', 'N° DE CUOTAS');
        $sheet->setCellValue('F14', $proposicion->NumeroCuotas);
        $sheet->setCellValue('E15', 'PLAZO');
        $sheet->setCellValue('F15', $plazo);

        $sheet->getStyle('E11:E15')->applyFromArray($styleVerdeBold);
        $sheet->getStyle('F12:F15')->getFont()->setBold(true);
        $sheet->getStyle('F12:F15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // --- 6. ZONA ---
        $sheet->mergeCells('A13:B13');
        $sheet->setCellValue('A13', $zona);
        $sheet->getStyle('A13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('A13')->getFont()->setBold(true)->setColor(new Color('FF0000'));
        $sheet->getStyle('A13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- INFO BANCARIA ---
        $sheet->setCellValue('K8', 'BCP CUENTA SOLES  305-4198556-0-62');
        $sheet->setCellValue('K9', 'CCI 00230500419855606216');
        $sheet->setCellValue('K10', 'JALUD SOCIEDAD ANONIMA CERRADA');
        $sheet->getStyle('K8:K10')->getFont()->setColor(new Color('FF0000'))->setSize(9);

        // --- GENERACIÓN DE TABLAS CON CUOTAS REALES ---
        $dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];
        $indiceFila = 0;

        // Calcular total pagado en TODO el crédito
        $totalPagadoEnCredito = 0;
        foreach ($credito->pagos as $pago) {
            $totalPagadoEnCredito += $pago->MontoPagado;
        }

        // Obtener pagos agrupados por cuota
        $pagosData = [];
        foreach ($credito->pagos as $pago) {
            if (!isset($pagosData[$pago->CuotaID])) {
                $pagosData[$pago->CuotaID] = [];
            }
            $pagosData[$pago->CuotaID][] = $pago;
        }

        // Filtrar la Cuota 0 si NO tiene pagos asociados
        $cuotas = $cuotas->filter(function ($cuota) use ($pagosData) {
            if ($cuota->NumeroCuota == 0) {
                return isset($pagosData[$cuota->CuotaID]) && count($pagosData[$cuota->CuotaID]) > 0;
            }
            return true;
        })->values();

        $saldoAcumulativo = $montoTotal + $totalInteres; // Comienza con el monto total

        foreach ($cuotas as $cuota) {
            $i = $indiceFila;

            // Lógica de bloques: 20 a la izquierda, 28 al medio, resto a la derecha
            if ($i < 20) {
                $colOffset = 'A';
                $currentRow = 17 + $i;
                $headerRow = 16;
            } elseif ($i < 48) {
                $colOffset = 'G';
                $currentRow = 9 + ($i - 20);
                $headerRow = 8;
            } else {
                $colOffset = 'K';
                $currentRow = 12 + ($i - 48);
                $headerRow = 11;
            }

            // Dibujar encabezados de tabla si es el inicio del bloque
            if ($i == 0 || $i == 20 || $i == 48) {
                $sheet->setCellValue($colOffset . $headerRow, 'FECHA');
                $sheet->getStyle($colOffset . $headerRow)->applyFromArray($styleHeaderTable);

                if ($i == 0) {
                    $efectivoCol1 = $this->nextCol($colOffset, 1);
                    $efectivoCol2 = $this->nextCol($colOffset, 2);
                    $sheet->mergeCells($efectivoCol1 . $headerRow . ':' . $efectivoCol2 . $headerRow);
                    $sheet->setCellValue($efectivoCol1 . $headerRow, 'EFECTIVO');
                    $sheet->getStyle($efectivoCol1 . $headerRow . ':' . $efectivoCol2 . $headerRow)->applyFromArray($styleHeaderTable);

                    $yapeCol1 = $this->nextCol($colOffset, 3);
                    $yapeCol2 = $this->nextCol($colOffset, 4);
                    $sheet->mergeCells($yapeCol1 . $headerRow . ':' . $yapeCol2 . $headerRow);
                    $sheet->setCellValue($yapeCol1 . $headerRow, 'YAPE - TRANSFERENCIA');
                    $sheet->getStyle($yapeCol1 . $headerRow . ':' . $yapeCol2 . $headerRow)->applyFromArray($styleHeaderTable);

                    $saldoCol = $this->nextCol($colOffset, 5);
                    $sheet->setCellValue($saldoCol . $headerRow, 'SALDO');
                    $sheet->getStyle($saldoCol . $headerRow)->applyFromArray($styleHeaderTable);
                } else {
                    $efectivoCol = $this->nextCol($colOffset, 1);
                    $sheet->setCellValue($efectivoCol . $headerRow, 'EFECTIVO');
                    $sheet->getStyle($efectivoCol . $headerRow)->applyFromArray($styleHeaderTable);

                    $yapeCol = $this->nextCol($colOffset, 2);
                    $sheet->setCellValue($yapeCol . $headerRow, 'YAPE - TRANSFERENCIA');
                    $sheet->getStyle($yapeCol . $headerRow)->applyFromArray($styleHeaderTable);

                    $saldoCol = $this->nextCol($colOffset, 3);
                    $sheet->setCellValue($saldoCol . $headerRow, 'SALDO');
                    $sheet->getStyle($saldoCol . $headerRow)->applyFromArray($styleHeaderTable);
                }
            }

            // Formato de fecha con día de la semana
            $fechaCuota = Carbon::parse($cuota->FechaVencimiento);
            $nombreDia = $dias[$fechaCuota->dayOfWeek];

            // Determinar si es domingo o feriado o pago inicial y construir el formato
            $esDomingo = $cuota->Estado === 'DOMINGO';
            $esFeriado = $cuota->Estado === 'FERIADO';
            $esPagoInicialFila = $cuota->NumeroCuota == 0;

            if ($esPagoInicialFila) {
                $fechaFormato = $fechaCuota->format('d/m/Y') . ' - PAGO INICIAL';
            } elseif ($esDomingo) {
                $fechaFormato = $fechaCuota->format('d/m/Y') . ' - ' . $nombreDia;
            } elseif ($esFeriado) {
                $fechaFormato = $fechaCuota->format('d/m/Y') . ' - ' . $nombreDia . ' - FERIADO';
            } else {
                $fechaFormato = $fechaCuota->format('d/m/Y') . ' - ' . $nombreDia;
            }

            // Datos y bordes verdes
            $sheet->setCellValue($colOffset . $currentRow, $fechaFormato);
            $sheet->getStyle($colOffset . $currentRow)->applyFromArray($styleBordeVerde);
            $sheet->getStyle($colOffset . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Si es domingo o feriado, marcar en rojo
            if ($esDomingo || $esFeriado) {
                $sheet->getStyle($colOffset . $currentRow)->getFont()->setColor(new Color('FF0000'));
            }

            // Calcular efectivo (sumar pagos de tipo EFECTIVO)
            $montoEfectivo = 0;
            $montoOtros = 0;

            if (isset($pagosData[$cuota->CuotaID])) {
                foreach ($pagosData[$cuota->CuotaID] as $pago) {
                    $montoEfectivo += $pago->MontoPagado;
                }
            }

            // Restar los pagos de esta cuota del saldo acumulativo
            $saldoAcumulativo -= $montoEfectivo;
            $saldoTotalCredito = max(0, $saldoAcumulativo); // No permitir negativos

            if ($i < 20) {
                // BLOQUE 1 - Columnas combinadas
                $efectivoCol1 = $this->nextCol($colOffset, 1);
                $efectivoCol2 = $this->nextCol($colOffset, 2);
                $sheet->mergeCells($efectivoCol1 . $currentRow . ':' . $efectivoCol2 . $currentRow);
                if ($montoEfectivo > 0) {
                    $sheet->setCellValue($efectivoCol1 . $currentRow, number_format($montoEfectivo, 2));
                    $sheet->getStyle($efectivoCol1 . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle($efectivoCol1 . $currentRow . ':' . $efectivoCol2 . $currentRow)->applyFromArray($styleBordeVerde);

                $yapeCol1 = $this->nextCol($colOffset, 3);
                $yapeCol2 = $this->nextCol($colOffset, 4);
                $sheet->mergeCells($yapeCol1 . $currentRow . ':' . $yapeCol2 . $currentRow);
                if ($montoOtros > 0) {
                    $sheet->setCellValue($yapeCol1 . $currentRow, number_format($montoOtros, 2));
                    $sheet->getStyle($yapeCol1 . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle($yapeCol1 . $currentRow . ':' . $yapeCol2 . $currentRow)->applyFromArray($styleBordeVerde);

                $saldoCol = $this->nextCol($colOffset, 5);
                $sheet->setCellValue($saldoCol . $currentRow, number_format($saldoTotalCredito, 2));
                $sheet->getStyle($saldoCol . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($saldoCol . $currentRow)->applyFromArray($styleBordeVerde);
            } else {
                // BLOQUES 2 y 3 - Columnas simples
                $efectivoCol = $this->nextCol($colOffset, 1);
                if ($montoEfectivo > 0) {
                    $sheet->setCellValue($efectivoCol . $currentRow, number_format($montoEfectivo, 2));
                    $sheet->getStyle($efectivoCol . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle($efectivoCol . $currentRow)->applyFromArray($styleBordeVerde);

                $yapeCol = $this->nextCol($colOffset, 2);
                if ($montoOtros > 0) {
                    $sheet->setCellValue($yapeCol . $currentRow, number_format($montoOtros, 2));
                    $sheet->getStyle($yapeCol . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle($yapeCol . $currentRow)->applyFromArray($styleBordeVerde);

                $saldoCol = $this->nextCol($colOffset, 3);
                $sheet->setCellValue($saldoCol . $currentRow, number_format($saldoTotalCredito, 2));
                $sheet->getStyle($saldoCol . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($saldoCol . $currentRow)->applyFromArray($styleBordeVerde);
            }

            $indiceFila++;
        }

        // Ajuste de anchos de columnas
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(25);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(25);
        $sheet->getColumnDimension('L')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(20);
        $sheet->getColumnDimension('N')->setWidth(12);
        $sheet->getColumnDimension('O')->setWidth(12);
        $sheet->getColumnDimension('P')->setWidth(12);

        $fileName = tempnam(sys_get_temp_dir(), 'libreta_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($fileName);

        return $fileName;
    }

    private function nextCol($col, $steps)
    {
        $alphabet = range('A', 'Z');
        $index = array_search($col, $alphabet);
        return $alphabet[$index + $steps];
    }
}