<?php

namespace Tests\Unit;

use App\Models\Gasto;
use App\Models\Motivo;
use App\Models\MovimientoTesoreria;
use App\Services\GerenciaBalanceDiarioService;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class GerenciaBalanceDiarioServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reconstruye_saldos_historicos_sin_confiar_en_snapshots_desfasados(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $transacciones = [
            ['fecha' => '2026-09-30', 'monto' => -200.00, 'saldo_antes' => 50000.00, 'saldo_despues' => 49800.00],
            ['fecha' => '2026-10-01', 'monto' => 100.00, 'saldo_antes' => 90000.00, 'saldo_despues' => 90100.00],
            ['fecha' => '2026-10-02', 'monto' => -50.00, 'saldo_antes' => 70000.00, 'saldo_despues' => 69950.00],
        ];
        $service = new GerenciaBalanceDiarioService();

        $calcularCierre = new ReflectionMethod($service, 'calcularSaldoCierre');
        $calcularInicial = new ReflectionMethod($service, 'calcularSaldoInicial');

        $this->assertSame(900.0, $calcularCierre->invoke($service, $transacciones, '2026-10-01', 850.0));
        $this->assertSame(800.0, $calcularInicial->invoke($service, $transacciones, '2026-10-01', 850.0));
    }

    public function test_asocia_extornos_al_gasto_por_su_identificador(): void
    {
        $extorno = new MovimientoTesoreria([
            'Tipo' => MovimientoTesoreria::TIPO_EXTORNO_GASTO,
            'GastoID' => 271,
            'FechaContable' => '2026-10-09',
        ]);
        $extorno->MovimientoTesoreriaID = 7;

        $service = new GerenciaBalanceDiarioService();
        $metodo = new ReflectionMethod($service, 'extornosPorGasto');
        $resultado = $metodo->invoke($service, collect([$extorno]));

        $this->assertSame('#7 (09/10/2026)', $resultado->get(271));
    }

    public function test_conserva_el_gasto_anulado_y_su_extorno_junto_al_gasto_vigente(): void
    {
        $anulado = $this->gasto(271, false, 15);
        $vigente = $this->gasto(272, true, 16);
        $movimientos = collect([
            $this->movimiento(6, MovimientoTesoreria::TIPO_EGRESO_GASTO, MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::GASTO, 271, $anulado),
            $this->movimiento(7, MovimientoTesoreria::TIPO_EXTORNO_GASTO, MovimientoTesoreria::GASTO, MovimientoTesoreria::CAJA_GERENCIA, 271, $anulado),
            $this->movimiento(8, MovimientoTesoreria::TIPO_EGRESO_GASTO, MovimientoTesoreria::CAJA_GERENCIA, MovimientoTesoreria::GASTO, 272, $vigente),
        ]);

        $service = new GerenciaBalanceDiarioService();
        $metodoMovimientos = new ReflectionMethod($service, 'movimientosParaBalance');
        $movimientos = $metodoMovimientos->invoke($service, $movimientos);
        $this->assertSame([6, 7, 8], $movimientos->map(fn (MovimientoTesoreria $movimiento): int => $movimiento->MovimientoTesoreriaID)->all());

        $metodoExtornos = new ReflectionMethod($service, 'extornosPorGasto');
        $extornos = $metodoExtornos->invoke($service, $movimientos);
        $metodoBalance = new ReflectionMethod($service, 'crearEstadoCuenta');
        $resultado = $metodoBalance->invoke(
            $service,
            'Caja Gerencia',
            MovimientoTesoreria::CAJA_GERENCIA,
            null,
            5000.00,
            '2026-10-01',
            $movimientos,
            collect(),
            collect(),
            collect(),
            collect(),
            $extornos,
            1,
        );

        $this->assertSame([6, 7, 8], array_column($resultado['movimientos'], 'id_orden'));
        $this->assertSame([-124.37, 124.37, -124.37], array_column($resultado['movimientos'], 'monto'));
        $this->assertStringContainsString('extorno(s): #7 (01/10/2026)', $resultado['movimientos'][0]['concepto']);
        $this->assertSame(124.37, $resultado['total_ingresos']);
        $this->assertSame(248.74, $resultado['total_salidas']);
    }

    private function movimiento(int $id, string $tipo, string $origen, string $destino, int $gastoId, Gasto $gasto): MovimientoTesoreria
    {
        $movimiento = new MovimientoTesoreria([
            'Tipo' => $tipo,
            'OrigenTipo' => $origen,
            'DestinoTipo' => $destino,
            'GastoID' => $gastoId,
            'Monto' => 124.37,
            'FechaContable' => '2026-10-01',
            'FechaMovimiento' => Carbon::parse('2026-10-01 08:00:00')->addMinutes($id),
            'Concepto' => $tipo === MovimientoTesoreria::TIPO_EXTORNO_GASTO ? 'Extorno de gasto #271' : 'Gasto #'.$gastoId,
        ]);
        $movimiento->MovimientoTesoreriaID = $id;
        $movimiento->setRelation('gasto', $gasto);

        return $movimiento;
    }

    private function gasto(int $id, bool $activo, int $motivoId): Gasto
    {
        $gasto = new Gasto([
            'TipoComprobanteGastoID' => 7,
            'Numero' => '001-2026',
            'FechaEmision' => '2026-10-01',
            'ProveedorID' => 16,
            'MotivoID' => $motivoId,
            'OrigenTesoreriaTipo' => MovimientoTesoreria::CAJA_GERENCIA,
            'Total' => 124.37,
            'Activo' => $activo,
        ]);
        $gasto->GastoID = $id;
        $gasto->setRelation('motivo', new Motivo(['Nombre' => 'Liquidación de beneficios sociales']));

        return $gasto;
    }
}
