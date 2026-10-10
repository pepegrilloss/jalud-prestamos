<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class GerenciaBalanceDiarioViewTest extends TestCase
{
    public function test_muestra_movimientos_en_orden_cronologico_y_subtotales_que_reconcilian_el_saldo(): void
    {
        $movimientos = [
            $this->movimiento(206, 'Trujillo', 2234.00, 145136.09),
            $this->movimiento(205, 'Chiclayo', -20000.00, 125136.09),
            $this->movimiento(207, 'Chiclayo', 9354.00, 134490.09),
        ];
        $secciones = [
            'remesas_entrada' => [$movimientos[0], $movimientos[2]],
            'remesas_salida' => [$movimientos[1]],
            'gastos' => [],
            'compras' => [],
            'otros' => [],
        ];
        $caja = [
            'nombre' => 'Caja Gerencia',
            'saldo_inicial' => 142902.09,
            'saldo_cierre' => 134490.09,
            'excedente' => 0.0,
            'movimientos' => $movimientos,
            'secciones' => $secciones,
            'total_ingresos' => 11588.00,
            'total_salidas' => 20000.00,
        ];

        $html = view('reportes.balance-diario-gerencia', [
            'sede' => (object) ['Nombre' => 'Gerencia'],
            'fecha' => Carbon::parse('2026-08-19'),
            'generadoEn' => Carbon::parse('2026-08-19 17:00:00'),
            'caja' => $caja,
            'cuentas' => [],
        ])->render();

        $posicionPrimeraEntrada = strpos($html, '2,234.00');
        $posicionSalida = strpos($html, '20,000.00');
        $posicionSegundaEntrada = strpos($html, '9,354.00');

        $this->assertNotFalse($posicionPrimeraEntrada);
        $this->assertNotFalse($posicionSalida);
        $this->assertNotFalse($posicionSegundaEntrada);
        $this->assertTrue($posicionPrimeraEntrada < $posicionSalida);
        $this->assertTrue($posicionSalida < $posicionSegundaEntrada);
        $this->assertStringContainsString('TOTAL INGRESO DE REMESAS', $html);
        $this->assertStringContainsString('11,588.00', $html);
        $this->assertStringContainsString('TOTAL SALIDA DE REMESAS', $html);
        $this->assertStringContainsString('20,000.00', $html);
        $this->assertStringContainsString('134,490.09', $html);
    }

    public function test_muestra_reintegros_de_gastos_y_compras_en_el_resumen_final(): void
    {
        $caja = [
            'nombre' => 'Caja Gerencia',
            'saldo_inicial' => 1000.00,
            'saldo_cierre' => 1125.50,
            'excedente' => 0.0,
            'movimientos' => [],
            'secciones' => [
                'remesas_entrada' => [],
                'remesas_salida' => [],
                'gastos' => [['ingreso' => 100.00, 'salida' => 0.0]],
                'compras' => [['ingreso' => 25.50, 'salida' => 0.0]],
                'otros' => [],
            ],
            'total_ingresos' => 125.50,
            'total_salidas' => 0.0,
        ];

        $html = view('reportes.balance-diario-gerencia', [
            'sede' => (object) ['Nombre' => 'Gerencia'],
            'fecha' => Carbon::parse('2026-08-19'),
            'generadoEn' => Carbon::parse('2026-08-19 17:00:00'),
            'caja' => $caja,
            'cuentas' => [],
        ])->render();

        $this->assertStringContainsString('Reintegros / ajustes de gastos (+):', $html);
        $this->assertStringContainsString('+100.00', $html);
        $this->assertStringContainsString('Reintegros / ajustes de compras (+):', $html);
        $this->assertStringContainsString('+25.50', $html);
    }

    private function movimiento(int $numero, string $contraparte, float $monto, float $saldo): array
    {
        return [
            'fecha' => '2026-08-19',
            'numero' => (string) $numero,
            'categoria' => 'remesa',
            'concepto' => $monto > 0 ? 'Remesa recepcion transferencia' : 'Remesa envio transferencia',
            'contraparte' => $contraparte,
            'cuenta' => 'Caja Abierta',
            'monto' => $monto,
            'saldo' => $saldo,
            'ingreso' => max($monto, 0),
            'salida' => abs(min($monto, 0)),
        ];
    }
}
