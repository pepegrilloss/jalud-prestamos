<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GerenciaCorregirFechasContablesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->crearEsquemaMinimo();
        DB::table('Sede')->insert(['SedeID' => 3, 'Nombre' => 'Gerencia']);
        DB::table('users')->insert(['id' => 24]);
        DB::table('tesoreria_cuentas')->insert([
            'CuentaTesoreriaID' => 2,
            'Banco' => 'BCP - CTA AHORROS - TRUJILLO',
            'NumeroCuenta' => '305-15479668-0-14',
            'SaldoActual' => 19142.42,
        ]);
        $this->insertarMovimientosObjetivo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_previsualiza_las_dos_fechas_sin_modificar_movimientos(): void
    {
        $antes = DB::table('tesoreria_movimientos')->orderBy('MovimientoTesoreriaID')->get()->toArray();

        $this->artisan('gerencia:corregir-fechas-contables', [
            '--transferencia-id' => '5',
            '--extorno-id' => '7',
        ])
            ->expectsOutputToContain('Previsualización: no se modificó ningún dato.')
            ->assertSuccessful();

        $this->assertEquals($antes, DB::table('tesoreria_movimientos')->orderBy('MovimientoTesoreriaID')->get()->toArray());
        $this->assertSame(0, DB::table('logs')->count());
    }

    public function test_aplica_solo_las_fechas_contables_objetivo_y_registra_auditoria(): void
    {
        $antes = DB::table('tesoreria_movimientos')->whereIn('MovimientoTesoreriaID', [5, 7])->get()->keyBy('MovimientoTesoreriaID');

        $this->artisan('gerencia:corregir-fechas-contables', [
            '--transferencia-id' => '5',
            '--extorno-id' => '7',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-CORRECCION-GERENCIA-2026-10-01',
        ])->assertSuccessful();

        $despues = DB::table('tesoreria_movimientos')->whereIn('MovimientoTesoreriaID', [5, 7])->get()->keyBy('MovimientoTesoreriaID');
        foreach ([5, 7] as $id) {
            $this->assertSame('2026-10-01', $despues[$id]->FechaContable);
            foreach (get_object_vars($antes[$id]) as $campo => $valor) {
                if ($campo !== 'FechaContable') {
                    $this->assertSame($valor, $despues[$id]->$campo);
                }
            }
        }

        $this->assertSame('2026-10-01', DB::table('tesoreria_movimientos')->where('MovimientoTesoreriaID', 6)->value('FechaContable'));
        $this->assertSame('2026-10-01', DB::table('tesoreria_movimientos')->where('MovimientoTesoreriaID', 8)->value('FechaContable'));
        $this->assertSame(19142.42, (float) DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', 2)->value('SaldoActual'));
        $this->assertSame(2, DB::table('logs')->count());
    }

    public function test_rechaza_ids_que_no_coinciden_con_los_movimientos_confirmados(): void
    {
        $this->artisan('gerencia:corregir-fechas-contables', [
            '--transferencia-id' => '6',
            '--extorno-id' => '7',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-CORRECCION-GERENCIA-2026-10-01',
        ])
            ->expectsOutputToContain('El movimiento indicado no coincide con la remesa de S/ 3,120 hacia la cuenta BCP de ahorros.')
            ->assertFailed();

        $this->assertSame('2026-10-09', DB::table('tesoreria_movimientos')->where('MovimientoTesoreriaID', 5)->value('FechaContable'));
        $this->assertSame(0, DB::table('logs')->count());
    }

    public function test_repetir_la_aplicacion_no_duplica_la_auditoria_ni_vuelve_a_cambiar_datos(): void
    {
        $opciones = [
            '--transferencia-id' => '5',
            '--extorno-id' => '7',
            '--usuario-id' => '24',
            '--aplicar' => true,
            '--confirmar' => 'APLICAR-CORRECCION-GERENCIA-2026-10-01',
        ];

        $this->artisan('gerencia:corregir-fechas-contables', $opciones)->assertSuccessful();
        $this->artisan('gerencia:corregir-fechas-contables', $opciones)
            ->expectsOutputToContain('Las dos fechas contables ya estaban corregidas; no se modificó ningún dato.')
            ->assertSuccessful();

        $this->assertSame(2, DB::table('logs')->count());
    }

    private function insertarMovimientosObjetivo(): void
    {
        DB::table('tesoreria_movimientos')->insert([
            $this->movimiento(5, 'TRANSFERENCIA', 'CAJA_GERENCIA', 'CUENTA_BANCARIA', 3120.00, '2026-10-09', null, 2),
            $this->movimiento(6, 'EGRESO_GASTO', 'CAJA_GERENCIA', 'GASTO', 124.37, '2026-10-01', 271),
            $this->movimiento(7, 'EXTORNO_GASTO', 'GASTO', 'CAJA_GERENCIA', 124.37, '2026-10-09', 271),
            $this->movimiento(8, 'EGRESO_GASTO', 'CAJA_GERENCIA', 'GASTO', 124.37, '2026-10-01', 272),
        ]);
    }

    private function movimiento(int $id, string $tipo, string $origen, string $destino, float $monto, string $fecha, ?int $gastoId = null, ?int $cuentaDestinoId = null): array
    {
        return [
            'MovimientoTesoreriaID' => $id,
            'Tipo' => $tipo,
            'OrigenTipo' => $origen,
            'CuentaOrigenID' => null,
            'CuentaOrigenNombre' => 'Caja Gerencia',
            'DestinoTipo' => $destino,
            'CuentaDestinoID' => $cuentaDestinoId,
            'CuentaDestinoNombre' => $cuentaDestinoId ? 'BCP - 305-15479668-0-14' : 'Caja Gerencia',
            'Monto' => $monto,
            'FechaContable' => $fecha,
            'FechaMovimiento' => $fecha.' 09:00:00',
            'Concepto' => $tipo,
            'UsuarioID' => 24,
            'GastoID' => $gastoId,
            'SaldoAnteriorOrigen' => 15000.00,
            'SaldoNuevoOrigen' => 11880.00,
            'SaldoAnteriorDestino' => 16022.42,
            'SaldoNuevoDestino' => 19142.42,
        ];
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('Sede', function (Blueprint $table): void {
            $table->unsignedBigInteger('SedeID')->primary();
            $table->string('Nombre');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
        });
        Schema::create('tesoreria_cuentas', function (Blueprint $table): void {
            $table->unsignedBigInteger('CuentaTesoreriaID')->primary();
            $table->string('Banco');
            $table->string('NumeroCuenta');
            $table->decimal('SaldoActual', 14, 2);
        });
        Schema::create('tesoreria_movimientos', function (Blueprint $table): void {
            $table->unsignedBigInteger('MovimientoTesoreriaID')->primary();
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
            $table->unsignedBigInteger('UsuarioID');
            $table->unsignedBigInteger('GastoID')->nullable();
            $table->decimal('SaldoAnteriorOrigen', 14, 2)->nullable();
            $table->decimal('SaldoNuevoOrigen', 14, 2)->nullable();
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
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
