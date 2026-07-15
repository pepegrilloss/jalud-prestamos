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
}
