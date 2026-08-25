<?php

namespace Tests\Unit;

use App\Http\Controllers\ReporteExportController;
use ReflectionMethod;
use Tests\TestCase;

class EficienciaCobranzaTest extends TestCase
{
    public function test_clasifica_clientes_en_grupos_excluyentes_y_scr_solo_si_realmente_salio(): void
    {
        $creditos = collect([
            1 => collect([
                $this->registro(1, 'ACTIVO', 'C-000011'),
                $this->registro(1, 'SALDADO', 'C-000012'),
            ]),
            2 => collect([$this->registro(2, 'SALDADO', 'C-000021')]),
            3 => collect([$this->registro(3, 'ACTIVO', 'C-000031')]),
        ]);
        $pagos = collect([
            1 => collect([$this->pago(1, 20, 'C-000011')]),
            2 => collect([$this->pago(2, 10, 'C-000021')]),
        ]);

        $controller = app(ReporteExportController::class);
        $method = new ReflectionMethod($controller, 'clasificarRegistrosEficiencia');
        $resultado = $method->invoke(
            $controller,
            $creditos,
            $pagos,
            ['SALDADO', 'REFINANCIADO', 'ELIMINADO'],
        );

        $this->assertCount(3, $resultado['activos']);
        $this->assertSame([1], $resultado['cancelaron']->keys()->all());
        $this->assertSame([3], $resultado['np']->keys()->all());
        $this->assertSame([2], $resultado['scr']->keys()->all());
        $this->assertSame(30.0, $resultado['monto_cobrado']);
    }

    private function registro(int $clienteId, string $estado, string $codigo): object
    {
        return (object) [
            'cliente_id' => $clienteId,
            'dni' => str_pad((string) $clienteId, 8, '0', STR_PAD_LEFT),
            'cliente' => "CLIENTE {$clienteId}",
            'codigo_credito' => $codigo,
            'estado_credito' => $estado,
            'fecha_generacion' => '2026-07-01 09:00:00',
            'fecha_saldamiento' => $estado === 'SALDADO' ? '2026-08-03 10:00:00' : null,
        ];
    }

    private function pago(int $clienteId, float $monto, string $codigo): object
    {
        return (object) [
            'cliente_id' => $clienteId,
            'dni' => str_pad((string) $clienteId, 8, '0', STR_PAD_LEFT),
            'cliente' => "CLIENTE {$clienteId}",
            'codigo_credito' => $codigo,
            'fecha_generacion' => '2026-07-01 09:00:00',
            'monto_pagado' => $monto,
        ];
    }
}
