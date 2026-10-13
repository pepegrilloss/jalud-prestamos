<?php

namespace Tests\Feature;

use App\Models\CuentaTesoreria;
use App\Models\CuotaPrestamoBancario;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use App\Models\PagoPrestamoBancario;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use App\Services\TesoreriaGerenciaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class PrestamoBancarioTesoreriaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        $this->crearEsquemaMinimo();

        foreach ([
            CuentaTesoreria::class,
            CuotaPrestamoBancario::class,
            FondoSede::class,
            MovimientoTesoreria::class,
            PagoPrestamoBancario::class,
            PrestamoBancario::class,
        ] as $modelo) {
            $modelo::flushEventListeners();
        }
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pago_de_cuota_descuenta_la_cuenta_bancaria_asociada_y_no_la_caja_gerencia(): void
    {
        [$prestamo, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);

        app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(400.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1000.0, (float) $fondo->fresh()->Saldo);
        $this->assertDatabaseHas('tesoreria_movimientos', [
            'Tipo' => MovimientoTesoreria::TIPO_PAGO_PRESTAMO_BANCARIO,
            'CuentaOrigenID' => $cuenta->CuentaTesoreriaID,
            'PrestamoBancarioID' => $prestamo->PrestamoBancarioID,
        ]);
    }

    public function test_pago_sin_cuenta_bancaria_descuenta_la_caja_gerencia(): void
    {
        [, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $cuota->prestamo()->update(['CuentaTesoreriaID' => null]);

        app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(900.0, (float) $fondo->fresh()->Saldo);
    }

    public function test_pago_de_tercero_permite_monto_variable_y_descuenta_caja_gerencia(): void
    {
        [$prestamo, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $prestamo->update([
            'TipoPrestamista' => PrestamoBancario::TIPO_TERCERO,
            'CuentaTesoreriaID' => null,
        ]);

        $pago = app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'Monto' => 135.50,
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(135.50, (float) $pago->Monto);
        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(864.50, (float) $fondo->fresh()->Saldo);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_CANCELADA, $cuota->fresh()->Estado);
    }

    public function test_cancelacion_anticipada_amortiza_solo_capital_y_anula_cuotas_pendientes(): void
    {
        [$prestamo, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $cuota->update(['Capital' => 80, 'Interes' => 20]);

        $cancelacion = app(PrestamoBancarioService::class)->cancelarAnticipadamente($prestamo, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(420.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1000.0, (float) $fondo->fresh()->Saldo);
        $this->assertSame(80.0, (float) $cancelacion->Monto);
        $this->assertSame(PagoPrestamoBancario::TIPO_CANCELACION_ANTICIPADA, $cancelacion->Tipo);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_ANULADA_ANTICIPADA, $cuota->fresh()->Estado);
        $this->assertSame(PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO, $prestamo->fresh()->Estado);
    }

    public function test_cancelacion_anticipada_de_tercero_descuenta_capital_desde_caja_gerencia(): void
    {
        [$prestamo, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $prestamo->update([
            'TipoPrestamista' => PrestamoBancario::TIPO_TERCERO,
            'CuentaTesoreriaID' => null,
        ]);
        $cuota->update(['Capital' => 80, 'Interes' => 20]);

        $cancelacion = app(PrestamoBancarioService::class)->cancelarAnticipadamente($prestamo, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(80.0, (float) $cancelacion->Monto);
        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(920.0, (float) $fondo->fresh()->Saldo);
        $this->assertSame(PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO, $prestamo->fresh()->Estado);
    }

    public function test_fecha_operativa_prevalece_en_pago_de_cuota_y_conserva_fecha_real_del_movimiento(): void
    {
        Carbon::setTestNow('2026-10-12 16:45:00');
        [, $cuota] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $this->abrirDiaGerencia('2026-10-02');

        $pago = app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'FechaContable' => '2026-10-12',
        ], 13);
        $movimiento = MovimientoTesoreria::findOrFail($pago->MovimientoTesoreriaID);

        $this->assertSame('2026-10-02', $pago->FechaContable->toDateString());
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12 16:45:00', $movimiento->FechaMovimiento->format('Y-m-d H:i:s'));
    }

    public function test_fecha_operativa_prevalece_en_cancelacion_anticipada(): void
    {
        Carbon::setTestNow('2026-10-12 16:45:00');
        [$prestamo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $this->abrirDiaGerencia('2026-10-02');

        $pago = app(PrestamoBancarioService::class)->cancelarAnticipadamente($prestamo, [
            'FechaContable' => '2026-10-12',
        ], 13);
        $movimiento = MovimientoTesoreria::findOrFail($pago->MovimientoTesoreriaID);

        $this->assertSame('2026-10-02', $pago->FechaContable->toDateString());
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12 16:45:00', $movimiento->FechaMovimiento->format('Y-m-d H:i:s'));
    }

    public function test_fecha_operativa_prevalece_en_extorno_de_pago_de_cuota(): void
    {
        Carbon::setTestNow('2026-10-12 16:45:00');
        [, $cuota] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $this->abrirDiaGerencia('2026-10-02');
        $pagoOriginal = app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'FechaContable' => '2026-10-12',
        ], 13);

        $extorno = app(PrestamoBancarioService::class)->extornarPago($pagoOriginal, [
            'FechaContable' => '2026-10-12',
        ], 13);
        $movimiento = MovimientoTesoreria::findOrFail($extorno->MovimientoTesoreriaID);

        $this->assertSame('2026-10-02', $extorno->FechaContable->toDateString());
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12 16:45:00', $movimiento->FechaMovimiento->format('Y-m-d H:i:s'));
    }

    public function test_fecha_operativa_prevalece_en_extorno_de_cancelacion_anticipada(): void
    {
        Carbon::setTestNow('2026-10-12 16:45:00');
        [$prestamo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $this->abrirDiaGerencia('2026-10-02');
        $cancelacion = app(PrestamoBancarioService::class)->cancelarAnticipadamente($prestamo, [
            'FechaContable' => '2026-10-12',
        ], 13);

        $extorno = app(PrestamoBancarioService::class)->extornarCancelacionAnticipada($cancelacion, [
            'FechaContable' => '2026-10-12',
        ], 13);
        $movimiento = MovimientoTesoreria::findOrFail($extorno->MovimientoTesoreriaID);

        $this->assertSame('2026-10-02', $extorno->FechaContable->toDateString());
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12 16:45:00', $movimiento->FechaMovimiento->format('Y-m-d H:i:s'));
    }

    public function test_apertura_de_cuenta_tesoreria_usa_fecha_operativa_y_hora_real(): void
    {
        Carbon::setTestNow('2026-10-12 16:45:00');
        $this->abrirDiaGerencia('2026-10-02');

        $cuenta = app(TesoreriaGerenciaService::class)->crearCuentaBancaria([
            'Banco' => 'BCP',
            'NumeroCuenta' => 'CTA-FECHA-TEST',
            'SaldoInicial' => 10000,
            'FechaContable' => '2026-10-12',
        ], 13);
        $movimiento = MovimientoTesoreria::query()
            ->where('Tipo', MovimientoTesoreria::TIPO_APERTURA)
            ->firstOrFail();

        $this->assertSame(10000.0, (float) $cuenta->SaldoActual);
        $this->assertSame(10000.0, (float) $movimiento->Monto);
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12 16:45:00', $movimiento->FechaMovimiento->format('Y-m-d H:i:s'));
    }

    public function test_saldamiento_historico_no_crea_pagos_ni_afecta_saldos(): void
    {
        [$prestamo, $cuota, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);

        $actualizado = app(PrestamoBancarioService::class)->registrarSaldamientoHistorico($prestamo, [
            'FechaSaldamientoHistorico' => '2026-07-15',
        ]);

        $this->assertSame(PrestamoBancario::ESTADO_CANCELADO, $actualizado->Estado);
        $this->assertTrue($actualizado->EsSaldadoHistorico);
        $this->assertSame('2026-07-15', $actualizado->FechaSaldamientoHistorico->toDateString());
        $this->assertSame(CuotaPrestamoBancario::ESTADO_SALDADA_HISTORICA, $cuota->fresh()->Estado);
        $this->assertSame(0, PagoPrestamoBancario::count());
        $this->assertSame(0, MovimientoTesoreria::count());
        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1000.0, (float) $fondo->fresh()->Saldo);
    }

    public function test_saldamiento_historico_rechaza_un_prestamo_que_ya_tiene_pagos(): void
    {
        [$prestamo, $cuota] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        app(PrestamoBancarioService::class)->pagarCuota($cuota, [
            'FechaContable' => '2026-07-15',
        ], 13);

        try {
            app(PrestamoBancarioService::class)->registrarSaldamientoHistorico($prestamo, [
                'FechaSaldamientoHistorico' => '2026-07-15',
            ]);
            $this->fail('Se esperaba que el préstamo con pago registrado no pudiera marcarse como histórico.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('prestamo', $exception->errors());
        }
    }

    public function test_registro_historico_parcial_no_crea_movimientos_y_deja_vigente_el_prestamo(): void
    {
        [$prestamo, $cuotaUno, $cuenta, $fondo] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $prestamo->update(['NumeroCuotas' => 3]);
        foreach ([2, 3] as $numero) {
            $prestamo->cuotas()->create([
                'Numero' => $numero,
                'FechaVencimiento' => '2026-08-30',
                'Capital' => 80,
                'Interes' => 20,
                'Comision' => 0,
                'Seguros' => 0,
                'MontoCuota' => 100,
                'SaldoDeuda' => 0,
                'Estado' => CuotaPrestamoBancario::ESTADO_PENDIENTE,
            ]);
        }

        $resultado = app(PrestamoBancarioService::class)->registrarCuotasSaldadasHistoricamente($prestamo, 2);

        $this->assertFalse($resultado['ya_registradas']);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_SALDADA_HISTORICA, $cuotaUno->fresh()->Estado);
        $this->assertNull($cuotaUno->fresh()->FechaPago);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_SALDADA_HISTORICA, $prestamo->cuotas()->where('Numero', 2)->first()->Estado);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_PENDIENTE, $prestamo->cuotas()->where('Numero', 3)->first()->Estado);
        $this->assertSame(PrestamoBancario::ESTADO_VIGENTE, $prestamo->fresh()->Estado);
        $this->assertSame(0, PagoPrestamoBancario::count());
        $this->assertSame(0, MovimientoTesoreria::count());
        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1000.0, (float) $fondo->fresh()->Saldo);

        $segundoIntento = app(PrestamoBancarioService::class)->registrarCuotasSaldadasHistoricamente($prestamo, 2);
        $this->assertTrue($segundoIntento['ya_registradas']);
        $this->assertSame(0, PagoPrestamoBancario::count());
        $this->assertSame(0, MovimientoTesoreria::count());
    }

    public function test_registro_historico_parcial_rechaza_cuotas_con_pago_y_no_cambia_las_demas(): void
    {
        [$prestamo, $cuotaUno] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $prestamo->update(['NumeroCuotas' => 3]);
        foreach ([2, 3] as $numero) {
            $prestamo->cuotas()->create([
                'Numero' => $numero,
                'FechaVencimiento' => '2026-08-30',
                'Capital' => 80,
                'Interes' => 20,
                'Comision' => 0,
                'Seguros' => 0,
                'MontoCuota' => 100,
                'SaldoDeuda' => 0,
                'Estado' => CuotaPrestamoBancario::ESTADO_PENDIENTE,
            ]);
        }
        app(PrestamoBancarioService::class)->pagarCuota($cuotaUno, ['FechaContable' => '2026-07-30'], 13);

        try {
            app(PrestamoBancarioService::class)->registrarCuotasSaldadasHistoricamente($prestamo, 2);
            $this->fail('No se deben registrar cuotas con pagos de tesorería como saldadas históricamente.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('cuotas', $exception->errors());
        }

        $this->assertSame(CuotaPrestamoBancario::ESTADO_CANCELADA, $cuotaUno->fresh()->Estado);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_PENDIENTE, $prestamo->cuotas()->where('Numero', 2)->first()->Estado);
        $this->assertSame(1, PagoPrestamoBancario::count());
        $this->assertSame(1, MovimientoTesoreria::count());
    }

    public function test_extorno_de_cancelacion_restituye_el_origen_y_reabre_el_prestamo(): void
    {
        [$prestamo, $cuota, $cuenta] = $this->crearPrestamoConUnaCuota(100, 500, 1000);
        $cancelacion = app(PrestamoBancarioService::class)->cancelarAnticipadamente($prestamo, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $extorno = app(PrestamoBancarioService::class)->extornarCancelacionAnticipada($cancelacion, [
            'FechaContable' => '2026-07-30',
        ], 13);

        $this->assertSame(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(PagoPrestamoBancario::TIPO_EXTORNO_CANCELACION, $extorno->Tipo);
        $this->assertSame($cancelacion->PagoPrestamoBancarioID, $extorno->PagoOriginalID);
        $this->assertSame(CuotaPrestamoBancario::ESTADO_PENDIENTE, $cuota->fresh()->Estado);
        $this->assertSame(PrestamoBancario::ESTADO_VIGENTE, $prestamo->fresh()->Estado);
    }

    private function crearPrestamoConUnaCuota(
        float $montoCuota,
        float $saldoCuenta,
        float $saldoCaja
    ): array {
        $sedeId = \DB::table('Sede')->insertGetId(['Nombre' => 'Gerencia']);
        $fondo = FondoSede::create(['SedeID' => $sedeId, 'Saldo' => $saldoCaja]);
        $cuenta = CuentaTesoreria::create([
            'Banco' => 'BBVA',
            'NumeroCuenta' => '001-TEST',
            'TipoCuenta' => 'BANCO',
            'SaldoActual' => $saldoCuenta,
            'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);
        $prestamo = PrestamoBancario::create([
            'CuentaTesoreriaID' => $cuenta->CuentaTesoreriaID,
            'TipoPrestamista' => PrestamoBancario::TIPO_BANCO,
            'Banco' => 'BBVA',
            'Cliente' => 'Cliente de prueba',
            'CuentaPrestamo' => 'PRESTAMO-001',
            'MontoPrestamo' => $montoCuota,
            'FechaDesembolso' => '2026-06-01',
            'FechaVencimiento' => '2026-07-30',
            'NumeroCuotas' => 1,
            'DiaPago' => 30,
            'PagoMensual' => $montoCuota,
            'TEA' => 0,
            'TED' => 0,
            'Estado' => PrestamoBancario::ESTADO_VIGENTE,
        ]);
        $cuota = $prestamo->cuotas()->create([
            'Numero' => 1,
            'FechaVencimiento' => '2026-07-30',
            'Capital' => $montoCuota,
            'Interes' => 0,
            'Comision' => 0,
            'Seguros' => 0,
            'MontoCuota' => $montoCuota,
            'SaldoDeuda' => 0,
            'Estado' => CuotaPrestamoBancario::ESTADO_PENDIENTE,
        ]);

        return [$prestamo, $cuota, $cuenta, $fondo];
    }

    private function abrirDiaGerencia(string $fecha): void
    {
        DB::table('apertura_cierre_dia')->insert([
            'SedeID' => 1,
            'Fecha' => $fecha,
            'EstadoDia' => 'ABIERTO',
        ]);
        Filament::setCurrentPanel(Filament::getPanel('gerencia'));
        session(['sede_activa' => 1]);
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('Sede', function (Blueprint $table): void {
            $table->id('SedeID');
            $table->string('Nombre');
        });
        Schema::create('apertura_cierre_dia', function (Blueprint $table): void {
            $table->id('AperturaCierreDiaID');
            $table->unsignedBigInteger('SedeID');
            $table->date('Fecha');
            $table->string('EstadoDia');
            $table->timestamps();
        });
        Schema::create('fondo_sedes', function (Blueprint $table): void {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID');
            $table->decimal('Saldo', 14, 2)->default(0);
            $table->decimal('SaldoCajaChica', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('tesoreria_cuentas', function (Blueprint $table): void {
            $table->id('CuentaTesoreriaID');
            $table->string('Banco');
            $table->string('NumeroCuenta');
            $table->string('TipoCuenta');
            $table->decimal('SaldoActual', 14, 2);
            $table->timestamp('FechaUltimoMovimiento')->nullable();
            $table->string('Estado');
            $table->timestamps();
        });
        Schema::create('tesoreria_prestamos_bancarios', function (Blueprint $table): void {
            $table->id('PrestamoBancarioID');
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable();
            $table->string('TipoPrestamista')->default(PrestamoBancario::TIPO_BANCO);
            $table->string('Banco');
            $table->string('Cliente');
            $table->string('CuentaPrestamo');
            $table->string('Operacion')->nullable();
            $table->decimal('MontoPrestamo', 14, 2);
            $table->date('FechaDesembolso');
            $table->date('FechaVencimiento');
            $table->unsignedSmallInteger('NumeroCuotas');
            $table->unsignedTinyInteger('DiaPago');
            $table->decimal('PagoMensual', 14, 2);
            $table->decimal('TEA', 9, 6);
            $table->decimal('TED', 9, 6);
            $table->string('Estado');
            $table->boolean('EsSaldadoHistorico')->default(false);
            $table->date('FechaSaldamientoHistorico')->nullable();
            $table->text('Observaciones')->nullable();
            $table->timestamps();
        });
        Schema::create('tesoreria_prestamo_cuotas', function (Blueprint $table): void {
            $table->id('CuotaPrestamoBancarioID');
            $table->unsignedBigInteger('PrestamoBancarioID');
            $table->unsignedSmallInteger('Numero');
            $table->date('FechaVencimiento');
            $table->decimal('Capital', 14, 2);
            $table->decimal('Interes', 14, 2);
            $table->decimal('Comision', 14, 2);
            $table->decimal('Seguros', 14, 2);
            $table->decimal('MontoCuota', 14, 2);
            $table->decimal('SaldoDeuda', 14, 2);
            $table->string('Estado');
            $table->date('FechaPago')->nullable();
            $table->timestamps();
        });
        Schema::create('tesoreria_movimientos', function (Blueprint $table): void {
            $table->id('MovimientoTesoreriaID');
            $table->string('Tipo');
            $table->string('OrigenTipo');
            $table->unsignedBigInteger('CuentaOrigenID')->nullable();
            $table->string('CuentaOrigenNombre');
            $table->string('DestinoTipo');
            $table->unsignedBigInteger('CuentaDestinoID')->nullable();
            $table->string('CuentaDestinoNombre');
            $table->decimal('Monto', 14, 2);
            $table->date('FechaContable');
            $table->timestamp('FechaMovimiento');
            $table->string('Concepto');
            $table->text('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->unsignedBigInteger('MovimientoOriginalID')->nullable();
            $table->unsignedBigInteger('PrestamoBancarioID')->nullable();
            $table->unsignedBigInteger('CuotaPrestamoBancarioID')->nullable();
            $table->decimal('SaldoAnteriorOrigen', 14, 2)->nullable();
            $table->decimal('SaldoNuevoOrigen', 14, 2)->nullable();
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('tesoreria_prestamo_pagos', function (Blueprint $table): void {
            $table->id('PagoPrestamoBancarioID');
            $table->string('Tipo')->default('PAGO_CUOTA');
            $table->unsignedBigInteger('PrestamoBancarioID');
            $table->unsignedBigInteger('CuotaPrestamoBancarioID')->nullable();
            $table->unsignedBigInteger('MovimientoTesoreriaID');
            $table->decimal('Monto', 14, 2);
            $table->date('FechaContable');
            $table->timestamp('FechaRegistro');
            $table->string('Concepto');
            $table->text('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->unsignedBigInteger('PagoOriginalID')->nullable();
            $table->timestamps();
        });
    }
}
