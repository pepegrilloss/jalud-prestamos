<?php

namespace Tests\Unit;

use App\Http\Controllers\ReporteExportController;
use Carbon\Carbon;
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

    public function test_un_credito_saldado_despues_se_mantiene_activo_en_la_fecha_consultada(): void
    {
        $creditos = collect([
            1 => collect([
                $this->registro(1, 'SALDADO', 'C-000011', '2026-09-05 10:00:00'),
            ]),
        ]);
        $pagos = collect([
            1 => collect([$this->pago(1, 50, 'C-000011')]),
        ]);

        $controller = app(ReporteExportController::class);
        $method = new ReflectionMethod($controller, 'clasificarRegistrosEficiencia');
        $resultado = $method->invoke(
            $controller,
            $creditos,
            $pagos,
            ['SALDADO', 'REFINANCIADO', 'ELIMINADO'],
            Carbon::parse('2026-09-01')->endOfDay(),
        );

        $this->assertCount(1, $resultado['activos']);
        $this->assertCount(1, $resultado['cancelaron']);
        $this->assertCount(0, $resultado['scr']);
    }

    public function test_el_pago_final_cuenta_como_cancelacion_y_scr_empieza_al_dia_siguiente(): void
    {
        $creditos = collect([
            1 => collect([
                $this->registro(1, 'SALDADO', 'C-000011', '2026-09-01 17:00:00'),
            ]),
        ]);
        $pagos = collect([
            1 => collect([$this->pago(1, 50, 'C-000011')]),
        ]);

        $controller = app(ReporteExportController::class);
        $method = new ReflectionMethod($controller, 'clasificarRegistrosEficiencia');
        $resultado = $method->invoke(
            $controller,
            $creditos,
            $pagos,
            ['SALDADO', 'REFINANCIADO', 'ELIMINADO'],
            Carbon::parse('2026-09-01')->endOfDay(),
        );

        $this->assertCount(1, $resultado['cancelaron']);
        $this->assertCount(0, $resultado['scr']);
    }

    private function registro(int $clienteId, string $estado, string $codigo, ?string $fechaSaldamiento = null): object
    {
        return (object) [
            'cliente_id' => $clienteId,
            'dni' => str_pad((string) $clienteId, 8, '0', STR_PAD_LEFT),
            'cliente' => "CLIENTE {$clienteId}",
            'codigo_credito' => $codigo,
            'estado_credito' => $estado,
            'fecha_generacion' => '2026-07-01 09:00:00',
            'fecha_saldamiento' => $fechaSaldamiento ?? ($estado === 'SALDADO' ? '2026-08-03 10:00:00' : null),
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
