<?php

namespace Tests\Unit;

use App\Services\RefinanciamientoPagoService;
use PHPUnit\Framework\TestCase;

class RefinanciamientoPagoServiceTest extends TestCase
{
    public function test_separa_el_excedente_como_pago_a_mayor(): void
    {
        $resultado = RefinanciamientoPagoService::calcularDistribucion(350, 325);

        $this->assertSame(325.0, $resultado['saldo_aplicado']);
        $this->assertSame(25.0, $resultado['pago_a_mayor']);
    }

    public function test_no_genera_pago_a_mayor_si_los_montos_coinciden(): void
    {
        $resultado = RefinanciamientoPagoService::calcularDistribucion(325, 325);

        $this->assertSame(325.0, $resultado['saldo_aplicado']);
        $this->assertSame(0.0, $resultado['pago_a_mayor']);
    }

    public function test_no_aplica_mas_del_monto_refinanciado_al_saldo(): void
    {
        $resultado = RefinanciamientoPagoService::calcularDistribucion(300, 325);

        $this->assertSame(300.0, $resultado['saldo_aplicado']);
        $this->assertSame(0.0, $resultado['pago_a_mayor']);
    }
}
