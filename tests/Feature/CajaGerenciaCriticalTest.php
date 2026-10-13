<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CuentaTesoreria;
use App\Services\CajaGerenciaService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class CajaGerenciaCriticalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite no disponible.');
        }

        Schema::dropAllTables();

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

        Schema::create('Compra', function (Blueprint $table): void {
            $table->id('CompraID');
            $table->unsignedBigInteger('SedeID');
            $table->decimal('Total', 14, 2);
            $table->string('TipoCompra');
            $table->string('EstadoPago');
            $table->boolean('Activo');
            $table->dateTime('FechaPago')->nullable();
            $table->unsignedBigInteger('UsuarioPagoID')->nullable();
            $table->string('OrigenTesoreriaTipo')->nullable();
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable();
            $table->text('Observaciones')->nullable();
            $table->dateTime('FechaCreacion')->nullable();
            $table->dateTime('FechaModificacion')->nullable();
        });

        Schema::create('tesoreria_cuentas', function (Blueprint $table): void {
            $table->id('CuentaTesoreriaID');
            $table->string('Banco');
            $table->string('NumeroCuenta');
            $table->string('TipoCuenta');
            $table->decimal('SaldoActual', 14, 2);
            $table->dateTime('FechaUltimoMovimiento')->nullable();
            $table->string('Estado');
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
            $table->dateTime('FechaMovimiento');
            $table->string('Concepto');
            $table->text('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->unsignedBigInteger('MovimientoOriginalID')->nullable();
            $table->unsignedBigInteger('CompraID')->nullable();
            $table->unsignedBigInteger('GastoID')->nullable();
            $table->decimal('SaldoAnteriorOrigen', 14, 2)->nullable();
            $table->decimal('SaldoNuevoOrigen', 14, 2)->nullable();
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
            $table->timestamps();
        });

        Carbon::setTestNow('2026-10-12 10:30:00');

        DB::table('Sede')->insert(['SedeID' => 1, 'Nombre' => 'Gerencia']);
        DB::table('apertura_cierre_dia')->insert([
            'SedeID' => 1,
            'Fecha' => '2026-10-02',
            'EstadoDia' => 'ABIERTO',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_segundo_intento_no_vuelve_a_descontar_una_factura_pagada(): void
    {
        $cuenta = CuentaTesoreria::create([
            'Banco' => 'BCP',
            'NumeroCuenta' => '001',
            'TipoCuenta' => 'BANCO',
            'SaldoActual' => 1000,
            'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);

        DB::table('Compra')->insert([
            'CompraID' => 10,
            'SedeID' => 1,
            'Total' => 500,
            'TipoCompra' => 'CREDITO',
            'EstadoPago' => 'PENDIENTE',
            'Activo' => 1,
            'FechaCreacion' => '2026-10-02 08:00:00',
            'FechaModificacion' => '2026-10-02 08:00:00',
        ]);

        $facturaObsoleta = Compra::withoutGlobalScope('sede')->findOrFail(10);
        $service = app(CajaGerenciaService::class);

        $service->pagarFacturaPendiente($facturaObsoleta, $cuenta->CuentaTesoreriaID, 7);

        $this->assertSame('PAGADO', DB::table('Compra')->where('CompraID', 10)->value('EstadoPago'));
        $this->assertEquals(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1, DB::table('tesoreria_movimientos')->where('CompraID', 10)->count());
        $this->assertSame('2026-10-02', DB::table('tesoreria_movimientos')->where('CompraID', 10)->value('FechaContable'));

        try {
            $service->pagarFacturaPendiente($facturaObsoleta, $cuenta->CuentaTesoreriaID, 8);
            $this->fail('El segundo pago debió ser rechazado.');
        } catch (ValidationException $e) {
            $this->assertSame('La factura ya no se encuentra pendiente de pago.', $e->errors()['factura'][0]);
        }

        $this->assertEquals(500.0, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame(1, DB::table('tesoreria_movimientos')->where('CompraID', 10)->count());
    }

    public function test_no_inactiva_una_cuenta_si_el_saldo_cambio_despues_de_cargar_el_formulario(): void
    {
        $cuentaObsoleta = CuentaTesoreria::create([
            'Banco' => 'BBVA',
            'NumeroCuenta' => '002',
            'TipoCuenta' => 'BANCO',
            'SaldoActual' => 0,
            'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);

        DB::table('tesoreria_cuentas')
            ->where('CuentaTesoreriaID', $cuentaObsoleta->CuentaTesoreriaID)
            ->update(['SaldoActual' => 250]);

        $this->expectException(ValidationException::class);

        try {
            app(CajaGerenciaService::class)->actualizarCuentaBancaria($cuentaObsoleta, [
                'Banco' => 'BBVA',
                'NumeroCuenta' => '002',
                'Estado' => CuentaTesoreria::ESTADO_INACTIVA,
            ]);
        } finally {
            $actual = CuentaTesoreria::findOrFail($cuentaObsoleta->CuentaTesoreriaID);
            $this->assertSame(CuentaTesoreria::ESTADO_ACTIVA, $actual->Estado);
            $this->assertEquals(250.0, (float) $actual->SaldoActual);
        }
    }
}
