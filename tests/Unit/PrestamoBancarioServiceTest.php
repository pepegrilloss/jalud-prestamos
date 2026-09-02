<?php

namespace Tests\Unit;

use App\Services\PrestamoBancarioService;
use Tests\TestCase;

class PrestamoBancarioServiceTest extends TestCase
{
    public function test_genera_cronograma_con_cuota_fija_y_saldo_final_en_cero(): void
    {
        $service = app(PrestamoBancarioService::class);
        $cronograma = $service->generarCronograma([
            'MontoPrestamo' => 12000,
            'FechaDesembolso' => '2024-10-14',
            'NumeroCuotas' => 24,
            'DiaPago' => 20,
            'TEA' => 38.39,
        ]);

        $this->assertCount(24, $cronograma);
        $this->assertSame('2024-11-20', $cronograma[0]['FechaVencimiento']);
        $this->assertSame('2026-10-20', $cronograma[23]['FechaVencimiento']);
        $this->assertSame(0.0, (float) $cronograma[23]['SaldoDeuda']);
        $this->assertEquals(12000.0, round(array_sum(array_column($cronograma, 'Capital')), 2));
    }

    public function test_calcula_ted_desde_tea(): void
    {
        $ted = app(PrestamoBancarioService::class)->calcularTed(38.39);

        $this->assertEqualsWithDelta(0.090292, $ted, 0.000001);
    }

    public function test_normaliza_cronograma_manual_y_recalcula_saldos(): void
    {
        $cronograma = app(PrestamoBancarioService::class)->normalizarCronogramaManual([
            ['Numero' => 1, 'Capital' => 350, 'Interes' => 20, 'Comision' => 5, 'Seguros' => 0],
            ['Numero' => 2, 'Capital' => 300, 'Interes' => 15, 'Comision' => 0, 'Seguros' => 0],
            ['Numero' => 3, 'Capital' => 300, 'Interes' => 5, 'Comision' => 0, 'Seguros' => 0],
        ], 1000);

        $this->assertSame(375.0, (float) $cronograma[0]['MontoCuota']);
        $this->assertSame(650.0, (float) $cronograma[0]['SaldoDeuda']);
        $this->assertSame(350.0, (float) $cronograma[1]['SaldoDeuda']);
        $this->assertSame(350.0, (float) $cronograma[2]['Capital']);
        $this->assertSame(355.0, (float) $cronograma[2]['MontoCuota']);
        $this->assertSame(0.0, (float) $cronograma[2]['SaldoDeuda']);
        $this->assertSame(1000.0, round(array_sum(array_column($cronograma, 'Capital')), 2));
    }
}
