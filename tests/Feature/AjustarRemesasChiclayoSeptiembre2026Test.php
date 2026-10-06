<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class AjustarRemesasChiclayoSeptiembre2026Test extends TestCase
{
    private const REMESAS = [
        [251, '2026-09-04', 10739.00, 10369.00, 37672, 37673],
        [263, '2026-09-10', 10479.00, 10480.60, 40577, 40578],
        [267, '2026-09-12', 7314.00, 7299.00, 41747, 41748],
        [300, '2026-09-23', 8568.50, 8588.50, 46865, 46866],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        $this->crearEsquema();
        $this->cargarDatos();
    }

    public function test_por_defecto_solo_muestra_vista_previa_sin_modificar_datos(): void
    {
        $this->artisan('fondos:ajustar-remesas-chiclayo-septiembre-2026')
            ->expectsOutputToContain('Vista previa solamente')
            ->assertSuccessful();

        $this->assertDatabaseHas('transferencia_sedes', ['TransferenciaID' => 251, 'Monto' => 10739.00]);
        $this->assertDatabaseHas('movimientos_fondo', ['MovimientoID' => 37672, 'Monto' => -10739.00]);
        $this->assertDatabaseHas('fondo_sedes', ['SedeID' => 1, 'Saldo' => 590502.10]);
    }

    public function test_aplicar_actualiza_solo_las_remesas_objetivo_y_snapshots_de_caja_abierta(): void
    {
        $this->artisan('fondos:ajustar-remesas-chiclayo-septiembre-2026', ['--aplicar' => true])
            ->expectsConfirmation('Se actualizarán únicamente estas cuatro remesas y sus saldos derivados. ¿Desea continuar?', 'yes')
            ->expectsOutputToContain('Ajuste aplicado correctamente')
            ->assertSuccessful();

        foreach (self::REMESAS as [$transferenciaId, , , $montoNuevo, $movimientoOrigenId, $movimientoDestinoId]) {
            $this->assertDatabaseHas('transferencia_sedes', [
                'TransferenciaID' => $transferenciaId,
                'Monto' => $montoNuevo,
                'MontoAprobado' => $montoNuevo,
            ]);
            $this->assertDatabaseHas('movimientos_fondo', ['MovimientoID' => $movimientoOrigenId, 'Monto' => -$montoNuevo]);
            $this->assertDatabaseHas('movimientos_fondo', ['MovimientoID' => $movimientoDestinoId, 'Monto' => $montoNuevo]);
        }

        $this->assertDatabaseHas('movimientos_fondo', [
            'MovimientoID' => 50000,
            'SaldoAnterior' => 100363.40,
            'SaldoNuevo' => 100368.40,
        ]);
        $this->assertDatabaseHas('movimientos_fondo', [
            'MovimientoID' => 50001,
            'SaldoAnterior' => 99636.60,
            'SaldoNuevo' => 99633.60,
        ]);
        $this->assertDatabaseHas('fondo_sedes', ['SedeID' => 1, 'Saldo' => 590865.50, 'SaldoCajaChica' => 100]);
        $this->assertDatabaseHas('fondo_sedes', ['SedeID' => 3, 'Saldo' => 241763.26, 'SaldoCajaChica' => 50]);
        $this->assertDatabaseHas('transferencia_sedes', ['TransferenciaID' => 298, 'Monto' => 30000.00]);
        $this->assertDatabaseHas('transferencia_sedes', ['TransferenciaID' => 301, 'Monto' => 10.00]);
    }

    public function test_se_detiene_sin_cambios_si_un_monto_no_coincide_con_la_revision(): void
    {
        DB::table('transferencia_sedes')->where('TransferenciaID', 251)->update(['Monto' => 10000]);

        $this->artisan('fondos:ajustar-remesas-chiclayo-septiembre-2026')
            ->expectsOutputToContain('ya no coincide con lo revisado')
            ->assertFailed();

        $this->assertDatabaseHas('movimientos_fondo', ['MovimientoID' => 37672, 'Monto' => -10739.00]);
        $this->assertDatabaseHas('fondo_sedes', ['SedeID' => 1, 'Saldo' => 590502.10]);
    }

    private function crearEsquema(): void
    {
        Schema::dropAllTables();

        Schema::create('Sede', function (Blueprint $table): void {
            $table->unsignedBigInteger('SedeID')->primary();
            $table->string('Nombre');
        });
        Schema::create('fondo_sedes', function (Blueprint $table): void {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID')->unique();
            $table->decimal('Saldo', 14, 2)->default(0);
            $table->decimal('SaldoCajaChica', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('transferencia_sedes', function (Blueprint $table): void {
            $table->unsignedBigInteger('TransferenciaID')->primary();
            $table->unsignedBigInteger('SedeOrigenID');
            $table->unsignedBigInteger('SedeDestinoID');
            $table->string('CuentaOrigen')->nullable();
            $table->string('CuentaDestino')->nullable();
            $table->boolean('EsSolicitudCapital')->default(false);
            $table->boolean('EsSolicitudGerencia')->default(false);
            $table->decimal('Monto', 14, 2);
            $table->decimal('MontoAprobado', 14, 2)->nullable();
            $table->string('Estado');
            $table->dateTime('FechaTransferencia');
            $table->timestamps();
        });
        Schema::create('movimientos_fondo', function (Blueprint $table): void {
            $table->unsignedBigInteger('MovimientoID')->primary();
            $table->unsignedBigInteger('SedeID');
            $table->string('Tipo');
            $table->decimal('Monto', 14, 2);
            $table->decimal('SaldoAnterior', 14, 2)->nullable();
            $table->decimal('SaldoNuevo', 14, 2)->nullable();
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->dateTime('FechaMovimiento');
            $table->timestamps();
        });
    }

    private function cargarDatos(): void
    {
        DB::table('Sede')->insert([
            ['SedeID' => 1, 'Nombre' => 'Chiclayo'],
            ['SedeID' => 3, 'Nombre' => 'Gerencia'],
        ]);
        DB::table('fondo_sedes')->insert([
            ['SedeID' => 1, 'Saldo' => 590502.10, 'SaldoCajaChica' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['SedeID' => 3, 'Saldo' => 242126.66, 'SaldoCajaChica' => 50, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach (self::REMESAS as [$transferenciaId, $fecha, $monto, , $movimientoOrigenId, $movimientoDestinoId]) {
            DB::table('transferencia_sedes')->insert([
                'TransferenciaID' => $transferenciaId,
                'SedeOrigenID' => 1,
                'SedeDestinoID' => 3,
                'CuentaOrigen' => 'CAJA_ABIERTA',
                'CuentaDestino' => 'CAJA_ABIERTA',
                'EsSolicitudCapital' => false,
                'EsSolicitudGerencia' => false,
                'Monto' => $monto,
                'MontoAprobado' => $monto,
                'Estado' => 'ACEPTADO',
                'FechaTransferencia' => $fecha.' 09:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('movimientos_fondo')->insert([
                [
                    'MovimientoID' => $movimientoOrigenId,
                    'SedeID' => 1,
                    'Tipo' => 'ENVIO_TRANSFERENCIA',
                    'Monto' => -$monto,
                    'SaldoAnterior' => 200000,
                    'SaldoNuevo' => 200000 - $monto,
                    'TransferenciaID' => $transferenciaId,
                    'FechaMovimiento' => $fecha.' 10:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'MovimientoID' => $movimientoDestinoId,
                    'SedeID' => 3,
                    'Tipo' => 'RECEPCION_TRANSFERENCIA',
                    'Monto' => $monto,
                    'SaldoAnterior' => 100000,
                    'SaldoNuevo' => 100000 + $monto,
                    'TransferenciaID' => $transferenciaId,
                    'FechaMovimiento' => $fecha.' 10:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        DB::table('transferencia_sedes')->insert([
            [
                'TransferenciaID' => 298, 'SedeOrigenID' => 3, 'SedeDestinoID' => 1,
                'CuentaOrigen' => 'CAJA_ABIERTA', 'CuentaDestino' => 'CAJA_ABIERTA',
                'EsSolicitudCapital' => false, 'EsSolicitudGerencia' => false,
                'Monto' => 30000, 'MontoAprobado' => 30000, 'Estado' => 'ACEPTADO',
                'FechaTransferencia' => '2026-09-23 11:00:00', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'TransferenciaID' => 301, 'SedeOrigenID' => 1, 'SedeDestinoID' => 3,
                'CuentaOrigen' => 'CAJA_ABIERTA', 'CuentaDestino' => 'CAJA_ABIERTA',
                'EsSolicitudCapital' => false, 'EsSolicitudGerencia' => false,
                'Monto' => 10, 'MontoAprobado' => 10, 'Estado' => 'ACEPTADO',
                'FechaTransferencia' => '2026-09-23 12:00:00', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('movimientos_fondo')->insert([
            [
                'MovimientoID' => 50000, 'SedeID' => 1, 'Tipo' => 'INGRESO_RECAUDO', 'Monto' => 5,
                'SaldoAnterior' => 100000, 'SaldoNuevo' => 100005, 'FechaMovimiento' => '2026-09-24 09:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'MovimientoID' => 50001, 'SedeID' => 3, 'Tipo' => 'EGRESO_GASTO', 'Monto' => -3,
                'SaldoAnterior' => 100000, 'SaldoNuevo' => 99997, 'FechaMovimiento' => '2026-09-24 09:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
