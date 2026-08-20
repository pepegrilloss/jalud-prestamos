<?php

namespace Tests\Unit;

use App\Services\ResolucionExcedenteService;
use PHPUnit\Framework\TestCase;

class ResolucionExcedenteServiceTest extends TestCase
{
    public function test_no_recalcula_el_estado_del_credito_usando_cuotas_referenciales(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Services/ResolucionExcedenteService.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString('verificarCreditoCancelado', $source);
        $this->assertStringNotContainsString("whereHas('cuota'", $source);
        $this->assertStringNotContainsString("cuotas()->where('Activo', 1)->sum('MontoCuota')", $source);
    }

    public function test_separa_la_parte_que_cubre_deuda_del_pago_a_mayor(): void
    {
        $this->assertSame([
            'monto_aplicar' => 65.0,
            'saldo_aplicado' => 62.0,
            'pago_a_mayor' => 3.0,
        ], ResolucionExcedenteService::calcularDistribucion(65, 62));
    }

    public function test_todo_el_exceso_es_a_mayor_si_el_credito_ya_esta_saldado(): void
    {
        $this->assertSame([
            'monto_aplicar' => 250.0,
            'saldo_aplicado' => 0.0,
            'pago_a_mayor' => 250.0,
        ], ResolucionExcedenteService::calcularDistribucion(250, 0));
    }
}
