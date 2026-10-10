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

    public function test_consolida_gasto_anulado_con_su_reemplazo_solo_si_el_extorno_es_completo(): void
    {
        $anulado = $this->gasto(271, false, 15);
        $vigente = $this->gasto(272, true, 16);
        $movimientos = collect([
            new MovimientoTesoreria([
                'Tipo' => MovimientoTesoreria::TIPO_EGRESO_GASTO,
                'OrigenTipo' => MovimientoTesoreria::CAJA_GERENCIA,
                'DestinoTipo' => MovimientoTesoreria::GASTO,
                'GastoID' => 271,
                'Monto' => 124.37,
            ]),
            new MovimientoTesoreria([
                'Tipo' => MovimientoTesoreria::TIPO_EXTORNO_GASTO,
                'OrigenTipo' => MovimientoTesoreria::GASTO,
                'DestinoTipo' => MovimientoTesoreria::CAJA_GERENCIA,
                'GastoID' => 271,
                'Monto' => 124.37,
            ]),
        ]);

        $resultado = $this->detectarReemplazos(collect([$anulado, $vigente]), $movimientos);

        $this->assertSame([271 => true], $resultado['omitir']);
        $this->assertSame([272 => 271], $resultado['activos']);

        $movimientos[1]->Monto = 100.00;
        $resultado = $this->detectarReemplazos(collect([$anulado, $vigente]), $movimientos);

        $this->assertSame([], $resultado['omitir']);
        $this->assertSame([], $resultado['activos']);
    }

    private function detectarReemplazos($gastos, $movimientos): array
    {
        $service = new GerenciaBalanceDiarioService();
        $metodo = new ReflectionMethod($service, 'detectarGastosReemplazados');

        return $metodo->invoke($service, $gastos->keyBy('GastoID'), $movimientos);
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
