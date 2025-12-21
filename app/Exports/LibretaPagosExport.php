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
        $zona = $cliente->negocio->zona->Nombre ?? 'N/A';

        $fechaInicio = Carbon::parse($credito->FechaGeneracion);
        $cuotas = $proposicion->NumeroCuotas;
        $montoCuota = $proposicion->MontoCuota;
        $montoTotal = $proposicion->MontoTotal;
        $plazo = $proposicion->Plazo;

        // Obtener días feriados de Perú (años que cubra el crédito)
        $feriadosData = [];
        try {
            $fechaInicio = Carbon::parse($credito->FechaGeneracion);
            $fechaFin = $fechaInicio->copy()->addDays($cuotas);
            $annoInicio = $fechaInicio->year;
            $annoFin = $fechaFin->year;
            
            // Obtener feriados de todos los años que cubre el crédito
            for ($anno = $annoInicio; $anno <= $annoFin; $anno++) {
                try {
                    $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                    $feriados = json_decode($response, true);
                    foreach ($feriados as $feriado) {
                        $feriadosData[$feriado['date']] = $feriado['localName'];
                    }
                } catch (\Exception $e) {
                    // Si falla para un año, continuar con los siguientes
                }
            }
        } catch (\Exception $e) {
            // Si falla la API, continuamos sin feriados
        }

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

        // --- 1. TÍTULO "CONTROL DE PAGOS" (Filas 8-9, Columnas B-E combinadas) ---
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

        // --- 2. NOMBRE DEL CLIENTE (Fila 10, Columnas A-F combinadas) ---
        $sheet->mergeCells('A10:F10');
        $sheet->setCellValue('A10', $cliente->NombresApellidos);
        $sheet->getStyle('A10')->applyFromArray($styleVerdeBold);
        $sheet->getStyle('A10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- 3. FECHA FE (Fila 11, Columnas A-B combinadas) ---
        $sheet->mergeCells('A11:B11');
        $sheet->setCellValue('A11', 'FE: ' . $fechaInicio->format('d/m/Y'));
        $sheet->getStyle('A11')->applyFromArray($styleVerdeBold);

        // --- 4. FECHA FV (Fila 12, Columnas A-B combinadas) ---
        $sheet->mergeCells('A12:B12');
        $fechaFV = $fechaInicio->copy()->addDays($cuotas);
        $cuotasExtra = 0;
        // Si la FV cae en domingo, se agregará una cuota extra para el lunes
        if ($fechaFV->dayOfWeek == 0) {
            $cuotasExtra = 1;
        }
        $sheet->setCellValue('A12', 'FV: ' . $fechaFV->format('d/m/Y'));
        $sheet->getStyle('A12')->applyFromArray($styleVerdeBold);

        // --- 5. BLOQUE DE DATOS DE CRÉDITO (Columna E labels, Columna F valores) ---
        $sheet->setCellValue('E11', 'PRINCIPAL');
        $sheet->setCellValue('E12', 'MONTO');
        $sheet->setCellValue('F12', number_format($montoTotal, 2));
        $sheet->setCellValue('E13', 'CUOTA');
        $sheet->setCellValue('F13', number_format($montoCuota, 2));
        $sheet->setCellValue('E14', 'N° DE CUOTAS');
        $sheet->setCellValue('F14', $cuotas);
        $sheet->setCellValue('E15', 'PLAZO');
        $sheet->setCellValue('F15', $plazo);
        
        $sheet->getStyle('E11:E15')->applyFromArray($styleVerdeBold);
        $sheet->getStyle('F12:F15')->getFont()->setBold(true);
        $sheet->getStyle('F12:F15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // --- 6. ZONA (Fila 13, Columnas A-B combinadas) ---
        $sheet->mergeCells('A13:B13');
        $sheet->setCellValue('A13', $zona); 
        $sheet->getStyle('A13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('A13')->getFont()->setBold(true)->setColor(new Color('FF0000'));
        $sheet->getStyle('A13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- INFO BANCARIA (Derecha) ---
        $sheet->setCellValue('K8', 'BCP CUENTA SOLES  305-4198556-0-62');
        $sheet->setCellValue('K9', 'CCI 00230500419855606216');
        $sheet->setCellValue('K10', 'JALUD SOCIEDAD ANONIMA CERRADA');
        $sheet->getStyle('K8:K10')->getFont()->setColor(new Color('FF0000'))->setSize(9);

        // --- GENERACIÓN DE TABLAS ---
        $fechaActual = $fechaInicio->copy()->addDay(); 
        $dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];
        $cuotasTotal = $cuotas + $cuotasExtra;
        
        for ($i = 0; $i < $cuotasTotal; $i++) {
            // Lógica de bloques: 18 a la izquierda, 26 al medio, resto a la derecha
            if ($i < 18) {
                $colOffset = 'A';
                $currentRow = 17 + $i;
                $headerRow = 16;
            } elseif ($i < 44) {
                $colOffset = 'G';
                $currentRow = 9 + ($i - 18);
                $headerRow = 8;
            } else {
                $colOffset = 'K';
                $currentRow = 13 + ($i - 44);
                $headerRow = 12;
            }

            // Dibujar encabezados de tabla si es el inicio del bloque
            if ($i == 0 || $i == 18 || $i == 44) {
                // FECHA - Columna A
                $sheet->setCellValue($colOffset . $headerRow, 'FECHA');
                $sheet->getStyle($colOffset . $headerRow)->applyFromArray($styleHeaderTable);
                
                if ($i == 0) {
                    // BLOQUE 1 - Columnas combinadas
                    // EFECTIVO - Columnas B y C combinadas
                    $efectivoCol1 = $this->nextCol($colOffset, 1);
                    $efectivoCol2 = $this->nextCol($colOffset, 2);
                    $sheet->mergeCells($efectivoCol1 . $headerRow . ':' . $efectivoCol2 . $headerRow);
                    $sheet->setCellValue($efectivoCol1 . $headerRow, 'EFECTIVO');
                    $sheet->getStyle($efectivoCol1 . $headerRow . ':' . $efectivoCol2 . $headerRow)->applyFromArray($styleHeaderTable);
                    
                    // YAPE - TRANSFERENCIA - Columnas D y E combinadas
                    $yapeCol1 = $this->nextCol($colOffset, 3);
                    $yapeCol2 = $this->nextCol($colOffset, 4);
                    $sheet->mergeCells($yapeCol1 . $headerRow . ':' . $yapeCol2 . $headerRow);
                    $sheet->setCellValue($yapeCol1 . $headerRow, 'YAPE - TRANSFERENCIA');
                    $sheet->getStyle($yapeCol1 . $headerRow . ':' . $yapeCol2 . $headerRow)->applyFromArray($styleHeaderTable);
                    
                    // SALDO - Columna F
                    $saldoCol = $this->nextCol($colOffset, 5);
                    $sheet->setCellValue($saldoCol . $headerRow, 'SALDO');
                    $sheet->getStyle($saldoCol . $headerRow)->applyFromArray($styleHeaderTable);
                } else {
                    // BLOQUES 2 y 3 - Columnas simples
                    // EFECTIVO - Columna B
                    $efectivoCol = $this->nextCol($colOffset, 1);
                    $sheet->setCellValue($efectivoCol . $headerRow, 'EFECTIVO');
                    $sheet->getStyle($efectivoCol . $headerRow)->applyFromArray($styleHeaderTable);
                    
                    // YAPE - TRANSFERENCIA - Columna C
                    $yapeCol = $this->nextCol($colOffset, 2);
                    $sheet->setCellValue($yapeCol . $headerRow, 'YAPE - TRANSFERENCIA');
                    $sheet->getStyle($yapeCol . $headerRow)->applyFromArray($styleHeaderTable);
                    
                    // SALDO - Columna D
                    $saldoCol = $this->nextCol($colOffset, 3);
                    $sheet->setCellValue($saldoCol . $headerRow, 'SALDO');
                    $sheet->getStyle($saldoCol . $headerRow)->applyFromArray($styleHeaderTable);
                }
            }

            // Formato de fecha con día de la semana
            $nombreDia = $dias[$fechaActual->dayOfWeek];
            $fechaFormato = $fechaActual->format('d/m/Y') . ' - ' . $nombreDia;
            $esDomingo = $fechaActual->dayOfWeek == 0;
            $esFeriado = isset($feriadosData[$fechaActual->format('Y-m-d')]);
            
            // Si es feriado, agregar al texto
            if ($esFeriado) {
                $fechaFormato .= ' - FERIADO';
            }

            // Datos y bordes verdes
            // FECHA - Columna A
            $sheet->setCellValue($colOffset . $currentRow, $fechaFormato);
            $sheet->getStyle($colOffset . $currentRow)->applyFromArray($styleBordeVerde);
            $sheet->getStyle($colOffset . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Si es domingo o feriado, color rojo
            if ($esDomingo || $esFeriado) {
                $sheet->getStyle($colOffset . $currentRow)->getFont()->setColor(new Color('FF0000'));
            }
            
            if ($i < 18) {
                // BLOQUE 1 - Columnas combinadas
                // EFECTIVO - Columnas B y C combinadas
                $efectivoCol1 = $this->nextCol($colOffset, 1);
                $efectivoCol2 = $this->nextCol($colOffset, 2);
                $sheet->mergeCells($efectivoCol1 . $currentRow . ':' . $efectivoCol2 . $currentRow);
                $sheet->getStyle($efectivoCol1 . $currentRow . ':' . $efectivoCol2 . $currentRow)->applyFromArray($styleBordeVerde);
                
                // YAPE - Columnas D y E combinadas
                $yapeCol1 = $this->nextCol($colOffset, 3);
                $yapeCol2 = $this->nextCol($colOffset, 4);
                $sheet->mergeCells($yapeCol1 . $currentRow . ':' . $yapeCol2 . $currentRow);
                $sheet->getStyle($yapeCol1 . $currentRow . ':' . $yapeCol2 . $currentRow)->applyFromArray($styleBordeVerde);
                
                // SALDO - Columna F
                $saldoCol = $this->nextCol($colOffset, 5);
                $sheet->getStyle($saldoCol . $currentRow)->applyFromArray($styleBordeVerde);
            } else {
                // BLOQUES 2 y 3 - Columnas simples
                // EFECTIVO - Columna B
                $efectivoCol = $this->nextCol($colOffset, 1);
                $sheet->getStyle($efectivoCol . $currentRow)->applyFromArray($styleBordeVerde);
                
                // YAPE - Columna C
                $yapeCol = $this->nextCol($colOffset, 2);
                $sheet->getStyle($yapeCol . $currentRow)->applyFromArray($styleBordeVerde);
                
                // SALDO - Columna D
                $saldoCol = $this->nextCol($colOffset, 3);
                $sheet->getStyle($saldoCol . $currentRow)->applyFromArray($styleBordeVerde);
            }

            $fechaActual->addDay(); 
        }

        // Ajuste de anchos de columnas
        // Columnas del BLOQUE 1 (A-F)
        $sheet->getColumnDimension('A')->setWidth(26); 
        $sheet->getColumnDimension('B')->setWidth(10); 
        $sheet->getColumnDimension('C')->setWidth(10); 
        $sheet->getColumnDimension('D')->setWidth(12); 
        $sheet->getColumnDimension('E')->setWidth(12); 
        $sheet->getColumnDimension('F')->setWidth(12); 

        // Columnas del BLOQUE 2 (G-J)
        $sheet->getColumnDimension('G')->setWidth(25); 
        $sheet->getColumnDimension('H')->setWidth(15); 
        $sheet->getColumnDimension('I')->setWidth(20); 
        $sheet->getColumnDimension('J')->setWidth(12); 

        // Columnas del BLOQUE 3 (K-N)
        $sheet->getColumnDimension('K')->setWidth(25); 
        $sheet->getColumnDimension('L')->setWidth(15); 
        $sheet->getColumnDimension('M')->setWidth(20); 
        $sheet->getColumnDimension('N')->setWidth(12); 

        // Columnas adicionales
        $sheet->getColumnDimension('O')->setWidth(12);
        $sheet->getColumnDimension('P')->setWidth(12);

        $fileName = tempnam(sys_get_temp_dir(), 'libreta_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($fileName);

        return $fileName;
    }

    private function nextCol($col, $steps) {
        $alphabet = range('A', 'Z');
        $index = array_search($col, $alphabet);
        return $alphabet[$index + $steps];
    }
}