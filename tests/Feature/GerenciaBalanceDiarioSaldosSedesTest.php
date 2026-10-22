<?php

namespace Tests\Feature;

use App\Services\GerenciaBalanceDiarioService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class GerenciaBalanceDiarioSaldosSedesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTablas();
    }

    public function test_reconstruye_caja_abierta_hasta_la_fecha_solicitada(): void
    {
        $this->insertarTransferencia(1, 99, 20, 'CUENTA_BANCARIA', 'CAJA_ABIERTA', 100.00);
        $this->insertarTransferencia(2, 20, 99, 'CAJA_ABIERTA', 'CUENTA_BANCARIA', 20.00);
        DB::table('pago')->insert([
            'CreditoID' => 1,
            'MontoPagado' => 30.00,
            'FechaPago' => '2026-10-14',
            'TipoConcepto' => 'C',
            'Activo' => true,
            'ExcluirBalanceDiario' => false,
            'EsPagoAMayorPorMora' => false,
            'SolicitudResolucionID' => null,
            'SedeID' => 20,
        ]);
        DB::table('ProposicionCredito')->insert(['ProposicionCreditoID' => 1, 'MontoTotal' => 40.00]);
        DB::table('Credito')->insert([
            'CreditoID' => 1,
            'ProposicionCreditoID' => 1,
            'Activo' => true,
            'SedeID' => 20,
            'FechaGeneracion' => '2026-10-14 09:00:00',
        ]);
        $this->insertarMovimiento(1, 'INGRESO_CAPITAL', 10.00);
        $this->insertarMovimiento(2, 'TRASLADO_CC_A_CA', 5.00);
        $this->insertarMovimiento(3, 'TRASLADO_CA_A_CC', -6.00);
        $this->insertarMovimiento(4, 'EGRESO_DEVOLUCION_EFECTIVO', -2.00);
        $this->insertarMovimiento(5, 'INGRESO_CAPITAL', 500.00, 'Posterior al corte', null, '2026-10-15 00:01:00');

        $saldo = $this->invocarSaldo('saldoCajaAbiertaHasta');

        $this->assertSame(77.0, $saldo);
        $this->assertSame(-149923.0, $this->invocarSaldo('saldoCajaAbiertaParaResumenGerencia'));
    }

    public function test_reconstruye_caja_chica_hasta_la_fecha_solicitada_y_excluye_ajustes(): void
    {
        $this->insertarTransferencia(3, 99, 20, 'CUENTA_BANCARIA', 'CAJA_CHICA', 15.00);
        $this->insertarTransferencia(4, 20, 99, 'CAJA_CHICA', 'CUENTA_BANCARIA', 7.00);
        $this->insertarTransferencia(5, 99, 20, 'CUENTA_BANCARIA', 'CAJA_CHICA', 100.00);
        $this->insertarMovimiento(1, 'INGRESO_CAJA_CHICA', 50.00, 'Ingreso manual');
        $this->insertarMovimiento(2, 'INGRESO_CAJA_CHICA', 100.00, 'AJUSTE contable');
        $this->insertarMovimiento(3, 'TRASLADO_CA_A_CC', 10.00, 'Traslado entre cajas');
        $this->insertarMovimiento(4, 'TRASLADO_CC_A_CA', -5.00);
        $this->insertarMovimiento(5, 'RECEPCION_TRANSFERENCIA', 15.00, 'Remesa', 3);
        $this->insertarMovimiento(6, 'ENVIO_TRANSFERENCIA', -7.00, 'Remesa', 4);
        $this->insertarMovimiento(7, 'RECEPCION_TRANSFERENCIA', 100.00, 'Reversión de remesa', 5);
        $this->insertarMovimiento(8, 'TRASLADO_CA_A_CC', 100.00, 'Ajuste manual');
        $this->insertarMovimiento(9, 'INGRESO_CAJA_CHICA', 1000.00, 'Ingreso posterior', null, '2026-10-15 00:01:00');
        DB::table('Gasto')->insert([
            'GastoID' => 1,
            'SedeID' => 20,
            'Activo' => true,
            'MetodoGasto' => 'CAJA CHICA',
            'FechaEmision' => '2026-10-14',
            'Total' => 10.00,
        ]);
        DB::table('Compra')->insert([
            'CompraID' => 1,
            'SedeID' => 20,
            'Activo' => true,
            'TipoCompra' => 'CONTADO',
            'FechaEmision' => '2026-10-14',
            'Total' => 8.00,
        ]);

        $saldo = $this->invocarSaldo('saldoCajaChicaHasta');

        $this->assertSame(45.0, $saldo);
    }

    private function invocarSaldo(string $metodo): float
    {
        $service = new GerenciaBalanceDiarioService();
        $reflection = new ReflectionMethod($service, $metodo);

        return $reflection->invoke($service, 20, Carbon::parse('2026-10-14')->endOfDay());
    }

    private function insertarTransferencia(int $id, int $sedeOrigen, int $sedeDestino, string $cuentaOrigen, string $cuentaDestino, float $monto): void
    {
        DB::table('transferencia_sedes')->insert([
            'TransferenciaID' => $id,
            'SedeOrigenID' => $sedeOrigen,
            'SedeDestinoID' => $sedeDestino,
            'CuentaOrigen' => $cuentaOrigen,
            'CuentaDestino' => $cuentaDestino,
            'EsSolicitudGerencia' => false,
            'Estado' => 'ACEPTADO',
            'Monto' => $monto,
            'FechaTransferencia' => '2026-10-13 10:00:00',
            'FechaRespuesta' => '2026-10-13 10:01:00',
        ]);
    }

    private function insertarMovimiento(
        int $id,
        string $tipo,
        float $monto,
        ?string $observacion = null,
        ?int $transferenciaId = null,
        string $fecha = '2026-10-14 12:00:00'
    ): void
    {
        DB::table('movimientos_fondo')->insert([
            'MovimientoID' => $id,
            'SedeID' => 20,
            'Tipo' => $tipo,
            'Monto' => $monto,
            'FechaMovimiento' => $fecha,
            'Observacion' => $observacion,
            'TransferenciaID' => $transferenciaId,
        ]);
    }

    private function crearTablas(): void
    {
        Schema::dropIfExists('solicitudes_resolucion_excedente');
        Schema::dropIfExists('excedentes');
        Schema::dropIfExists('Credito');
        Schema::dropIfExists('ProposicionCredito');
        Schema::dropIfExists('pago');
        Schema::dropIfExists('Compra');
        Schema::dropIfExists('Gasto');
        Schema::dropIfExists('movimientos_fondo');
        Schema::dropIfExists('transferencia_sedes');

        Schema::create('transferencia_sedes', function (Blueprint $table): void {
            $table->unsignedInteger('TransferenciaID')->primary();
            $table->unsignedInteger('SedeOrigenID');
            $table->unsignedInteger('SedeDestinoID');
            $table->string('CuentaOrigen');
            $table->string('CuentaDestino');
            $table->boolean('EsSolicitudGerencia')->nullable();
            $table->string('Estado');
            $table->decimal('Monto', 12, 2);
            $table->dateTime('FechaTransferencia');
            $table->dateTime('FechaRespuesta')->nullable();
        });
        Schema::create('movimientos_fondo', function (Blueprint $table): void {
            $table->unsignedInteger('MovimientoID')->primary();
            $table->unsignedInteger('SedeID');
            $table->string('Tipo');
            $table->decimal('Monto', 12, 2);
            $table->dateTime('FechaMovimiento');
            $table->text('Observacion')->nullable();
            $table->unsignedInteger('TransferenciaID')->nullable();
        });
        Schema::create('pago', function (Blueprint $table): void {
            $table->increments('PagoID');
            $table->unsignedInteger('CreditoID');
            $table->decimal('MontoPagado', 12, 2);
            $table->date('FechaPago');
            $table->string('TipoConcepto')->nullable();
            $table->boolean('Activo');
            $table->boolean('ExcluirBalanceDiario');
            $table->boolean('EsPagoAMayorPorMora');
            $table->unsignedInteger('SolicitudResolucionID')->nullable();
            $table->unsignedInteger('SedeID');
            $table->text('Comentario')->nullable();
        });
        Schema::create('Credito', function (Blueprint $table): void {
            $table->unsignedInteger('CreditoID')->primary();
            $table->unsignedInteger('ProposicionCreditoID');
            $table->boolean('Activo');
            $table->unsignedInteger('SedeID');
            $table->dateTime('FechaGeneracion');
        });
        Schema::create('ProposicionCredito', function (Blueprint $table): void {
            $table->unsignedInteger('ProposicionCreditoID')->primary();
            $table->decimal('MontoTotal', 12, 2);
        });
        Schema::create('excedentes', function (Blueprint $table): void {
            $table->unsignedInteger('ExcedenteID')->primary();
            $table->unsignedInteger('SedeID');
            $table->boolean('Activo');
            $table->string('Cuenta')->nullable();
            $table->date('Fecha');
            $table->decimal('Monto', 12, 2);
        });
        Schema::create('solicitudes_resolucion_excedente', function (Blueprint $table): void {
            $table->unsignedInteger('SolicitudID')->primary();
            $table->unsignedInteger('ExcedenteID');
            $table->string('Estado');
            $table->decimal('MontoAplicar', 12, 2);
        });
        Schema::create('Gasto', function (Blueprint $table): void {
            $table->unsignedInteger('GastoID')->primary();
            $table->unsignedInteger('SedeID');
            $table->boolean('Activo');
            $table->string('MetodoGasto');
            $table->date('FechaEmision');
            $table->decimal('Total', 12, 2);
        });
        Schema::create('Compra', function (Blueprint $table): void {
            $table->unsignedInteger('CompraID')->primary();
            $table->unsignedInteger('SedeID');
            $table->boolean('Activo');
            $table->string('TipoCompra');
            $table->date('FechaEmision');
            $table->decimal('Total', 12, 2);
        });
    }
}
