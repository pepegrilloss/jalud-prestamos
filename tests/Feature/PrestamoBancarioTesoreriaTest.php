<?php

namespace Tests\Feature;

use App\Models\CuentaTesoreria;
use App\Models\CuotaPrestamoBancario;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use App\Models\PagoPrestamoBancario;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use Illuminate\Database\Schema\Blueprint;
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

    private function crearEsquemaMinimo(): void
    {
        Schema::create('Sede', function (Blueprint $table): void {
            $table->id('SedeID');
            $table->string('Nombre');
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
