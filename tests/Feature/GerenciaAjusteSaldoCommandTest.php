<?php

namespace Tests\Feature;

use App\Services\GerenciaBalanceDiarioService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GerenciaAjusteSaldoCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');
        $this->crearEsquemaMinimo();

        DB::table('Sede')->insert([
            ['SedeID' => 3, 'Nombre' => 'Gerencia'],
            ['SedeID' => 1, 'Nombre' => 'Chiclayo'],
        ]);
        DB::table('fondo_sedes')->insert([
            ['FondoSedeID' => 1, 'SedeID' => 3, 'Saldo' => 25000, 'created_at' => now(), 'updated_at' => now()],
            ['FondoSedeID' => 2, 'SedeID' => 1, 'Saldo' => 9000, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('users')->insert(['id' => 24]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_previsualiza_sin_modificar_saldos_ni_registrar_movimientos(): void
    {
        $this->mockBalance(1000.00);

        $this->artisan('gerencia:caja-ajustar-saldo', [
            '--fecha' => '2026-09-30',
            '--saldo' => '5092.70',
            '--motivo' => 'Saldo confirmado al cierre',
        ])
            ->expectsOutputToContain('Previsualización: no se modificó ningún dato.')
            ->assertSuccessful();

        $this->assertSame(25000.00, round((float) DB::table('fondo_sedes')->where('SedeID', 3)->value('Saldo'), 2));
        $this->assertSame(9000.00, round((float) DB::table('fondo_sedes')->where('SedeID', 1)->value('Saldo'), 2));
        $this->assertSame(0, DB::table('movimientos_fondo')->count());
    }

    public function test_aplica_ajuste_solo_a_caja_gerencia_y_deja_asiento_auditado(): void
    {
        $this->mockBalance(7000.00);

        $this->artisan('gerencia:caja-ajustar-saldo', [
            '--fecha' => '2026-09-30',
            '--saldo' => '5092.70',
            '--motivo' => 'Saldo confirmado en arqueo',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-AJUSTE-GERENCIA-2026-09-30',
        ])->assertSuccessful();

        $this->assertSame(23092.70, round((float) DB::table('fondo_sedes')->where('SedeID', 3)->value('Saldo'), 2));
        $this->assertSame(9000.00, round((float) DB::table('fondo_sedes')->where('SedeID', 1)->value('Saldo'), 2));

        $movimiento = DB::table('movimientos_fondo')->sole();
        $this->assertSame(3, (int) $movimiento->SedeID);
        $this->assertSame('AJUSTE_CONCILIACION_CAJA', $movimiento->Tipo);
        $this->assertSame(-1907.30, round((float) $movimiento->Monto, 2));
        $this->assertSame(24, (int) $movimiento->UsuarioID);
        $this->assertStringContainsString('[AJUSTE_CIERRE_GERENCIA:2026-09-30]', $movimiento->Observacion);
        $this->assertStringContainsString('Saldo confirmado en arqueo', $movimiento->Observacion);
        $this->assertStringNotContainsString('S/ S/', $movimiento->Observacion);
        $this->assertSame('2026-09-30 23:59:59', $movimiento->FechaMovimiento);

        $logs = DB::table('logs')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([24], $logs->pluck('user_id')->unique()->values()->all());
        $this->assertSame([3], $logs->pluck('SedeID')->unique()->values()->all());
    }

    public function test_rechaza_ajuste_que_dejaria_la_caja_operativa_en_negativo(): void
    {
        DB::table('fondo_sedes')->where('SedeID', 3)->update(['Saldo' => 1000]);
        $this->mockBalance(7000.00);

        $this->artisan('gerencia:caja-ajustar-saldo', [
            '--fecha' => '2026-09-30',
            '--saldo' => '0.00',
            '--motivo' => 'Prueba de límite de saldo',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-AJUSTE-GERENCIA-2026-09-30',
        ])
            ->expectsOutputToContain('El ajuste dejaría el saldo operativo de Caja Gerencia en negativo.')
            ->assertFailed();

        $this->assertSame(1000.00, round((float) DB::table('fondo_sedes')->where('SedeID', 3)->value('Saldo'), 2));
        $this->assertSame(0, DB::table('movimientos_fondo')->count());
    }

    public function test_impide_aplicar_dos_ajustes_de_cierre_para_la_misma_fecha(): void
    {
        DB::table('movimientos_fondo')->insert([
            'SedeID' => 3,
            'Tipo' => 'AJUSTE_CONCILIACION_CAJA',
            'Monto' => -1,
            'SaldoAnterior' => 25000,
            'SaldoNuevo' => 24999,
            'UsuarioID' => 24,
            'Observacion' => '[AJUSTE_CIERRE_GERENCIA:2026-09-30] Ajuste previo',
            'FechaMovimiento' => '2026-09-30 23:59:59',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->mockBalance(7000.00, 0);

        $this->artisan('gerencia:caja-ajustar-saldo', [
            '--fecha' => '2026-09-30',
            '--saldo' => '5092.70',
            '--motivo' => 'Prueba de idempotencia',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-AJUSTE-GERENCIA-2026-09-30',
        ])
            ->expectsOutputToContain('Ya existe un ajuste de cierre de Gerencia para 2026-09-30; no se aplicó otro.')
            ->assertFailed();

        $this->assertSame(25000.00, round((float) DB::table('fondo_sedes')->where('SedeID', 3)->value('Saldo'), 2));
        $this->assertSame(1, DB::table('movimientos_fondo')->count());
    }

    private function mockBalance(float $cierre, int $veces = 1): void
    {
        $balance = Mockery::mock(GerenciaBalanceDiarioService::class);
        $balance->shouldReceive('generar')
            ->times($veces)
            ->with('2026-09-30')
            ->andReturn(['caja' => ['saldo_cierre' => $cierre]]);

        $this->app->instance(GerenciaBalanceDiarioService::class, $balance);
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('Sede', function (Blueprint $table): void {
            $table->unsignedBigInteger('SedeID')->primary();
            $table->string('Nombre');
        });

        Schema::create('fondo_sedes', function (Blueprint $table): void {
            $table->bigIncrements('FondoSedeID');
            $table->unsignedBigInteger('SedeID');
            $table->decimal('Saldo', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('movimientos_fondo', function (Blueprint $table): void {
            $table->bigIncrements('MovimientoID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Tipo', 50);
            $table->decimal('Monto', 14, 2);
            $table->decimal('SaldoAnterior', 14, 2)->default(0);
            $table->decimal('SaldoNuevo', 14, 2)->default(0);
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->string('Observacion', 500)->nullable();
            $table->dateTime('FechaMovimiento')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
        });

        Schema::create('logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('accion');
            $table->string('modelo');
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('platform')->nullable();
            $table->unsignedBigInteger('SedeID')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
