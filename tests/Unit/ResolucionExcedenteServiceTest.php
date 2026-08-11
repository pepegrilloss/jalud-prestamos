<?php

namespace Tests\Unit;

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
}
