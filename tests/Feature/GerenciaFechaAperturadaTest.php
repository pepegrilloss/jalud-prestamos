<?php

namespace Tests\Feature;

use App\Models\AperturaCierreDia;
use App\Models\CuentaTesoreria;
use App\Models\MovimientoTesoreria;
use App\Models\MovimientoFondo;
use App\Models\TransferenciaSede;
use App\Services\DateFieldResolver;
use App\Services\FondoSedeService;
use App\Services\TesoreriaGerenciaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class GerenciaFechaAperturadaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite no disponible.');
        }

        Schema::dropAllTables();
        Schema::create('Sede', function (Blueprint $table) {
            $table->id('SedeID');
            $table->string('Nombre');
        });
        Schema::create('apertura_cierre_dia', function (Blueprint $table) {
            $table->id('AperturaCierreDiaID');
            $table->unsignedBigInteger('SedeID');
            $table->date('Fecha');
            $table->string('EstadoDia');
            $table->timestamps();
        });
        Schema::create('fondo_sedes', function (Blueprint $table) {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID');
            $table->decimal('Saldo', 14, 2);
            $table->decimal('SaldoCajaChica', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('transferencia_sedes', function (Blueprint $table) {
            $table->id('TransferenciaID');
            $table->unsignedBigInteger('SedeOrigenID');
            $table->unsignedBigInteger('SedeDestinoID');
            $table->string('CuentaOrigen');
            $table->string('CuentaDestino');
            $table->boolean('EsSolicitudCapital');
            $table->boolean('EsSolicitudGerencia');
            $table->unsignedBigInteger('UsuarioOrigenID');
            $table->decimal('Monto', 14, 2);
            $table->string('Estado');
            $table->string('Observacion')->nullable();
            $table->dateTime('FechaTransferencia');
            $table->timestamps();
        });
        Schema::create('movimientos_fondo', function (Blueprint $table) {
            $table->id('MovimientoID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Tipo');
            $table->decimal('Monto', 14, 2);
            $table->decimal('SaldoAnterior', 14, 2);
            $table->decimal('SaldoNuevo', 14, 2);
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->string('Observacion')->nullable();
            $table->dateTime('FechaMovimiento');
            $table->timestamps();
        });
        Schema::create('tesoreria_cuentas', function (Blueprint $table) {
            $table->id('CuentaTesoreriaID');
            $table->string('Banco');
            $table->string('NumeroCuenta');
            $table->string('TipoCuenta');
            $table->decimal('SaldoActual', 14, 2);
            $table->dateTime('FechaUltimoMovimiento')->nullable();
            $table->string('Estado');
            $table->timestamps();
        });
        Schema::create('tesoreria_movimientos', function (Blueprint $table) {
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
            $table->string('Observaciones')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->unsignedBigInteger('MovimientoOriginalID')->nullable();
            $table->unsignedBigInteger('CompraID')->nullable();
            $table->unsignedBigInteger('GastoID')->nullable();
            $table->decimal('SaldoAnteriorOrigen', 14, 2);
            $table->decimal('SaldoNuevoOrigen', 14, 2);
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('accion');
            $table->string('modelo');
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('platform')->nullable();
            $table->dateTime('created_at');
            $table->unsignedBigInteger('SedeID')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('Compra', function (Blueprint $table) {
            $table->id('CompraID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Numero');
            $table->dateTime('FechaEmision');
            $table->dateTime('FechaCreacion');
            $table->decimal('Total', 14, 2);
            $table->string('TipoCompra');
            $table->string('EstadoPago');
            $table->boolean('Activo');
            $table->string('OrigenTesoreriaTipo');
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable();
        });

        Carbon::setTestNow('2026-10-12 10:30:00');
        Filament::setCurrentPanel(Filament::getPanel('gerencia'));
        session(['sede_activa' => 1]);
        DB::table('Sede')->insert([
            ['SedeID' => 1, 'Nombre' => 'Gerencia'],
            ['SedeID' => 2, 'Nombre' => 'Chiclayo'],
        ]);
        DB::table('apertura_cierre_dia')->insert([
            'SedeID' => 1,
            'Fecha' => '2026-10-02',
            'EstadoDia' => 'ABIERTO',
        ]);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_indicador_usa_la_apertura_anterior_activa(): void
    {
        $this->assertSame('ABIERTO', AperturaCierreDia::estadoDiaActual());
        $this->assertSame('2026-10-02', DateFieldResolver::getFechaAbierta()?->toDateString());

        DB::table('apertura_cierre_dia')->insert([
            'SedeID' => 2,
            'Fecha' => '2026-10-11',
            'EstadoDia' => 'ABIERTO',
        ]);

        $this->assertSame('2026-10-02', DateFieldResolver::getFechaAbierta()?->toDateString());

        DB::table('apertura_cierre_dia')->where('SedeID', 1)->update(['EstadoDia' => 'CERRADO']);
        $this->assertSame('CERRADO', AperturaCierreDia::estadoDiaActual());
        $this->assertNull(DateFieldResolver::getFechaAbierta());
    }

    public function test_remesa_guarda_fecha_operativa_y_momento_real_de_registro(): void
    {
        DB::table('fondo_sedes')->insert(['SedeID' => 1, 'Saldo' => 500]);

        $transferencia = app(FondoSedeService::class)->crearTransferencia(
            1, 2, 100, 7, 'Remesa del dia abierto'
        );

        $this->assertSame('2026-10-02', $transferencia->FechaTransferencia->toDateString());
        $this->assertSame('2026-10-12', $transferencia->created_at->toDateString());

        $movimiento = MovimientoFondo::create([
            'SedeID' => 1, 'Tipo' => 'ENVIO_TRANSFERENCIA', 'Monto' => -100,
            'SaldoAnterior' => 500, 'SaldoNuevo' => 400,
            'TransferenciaID' => $transferencia->TransferenciaID, 'UsuarioID' => 7,
        ]);
        $this->assertSame('2026-10-02', $movimiento->FechaMovimiento->toDateString());
        $this->assertSame('2026-10-12', $movimiento->created_at->toDateString());
    }

    public function test_transferencia_bancaria_usa_la_fecha_del_dia_abierto(): void
    {
        $origen = CuentaTesoreria::create([
            'Banco' => 'BCP', 'NumeroCuenta' => '1', 'TipoCuenta' => 'BANCO',
            'SaldoActual' => 500, 'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);
        $destino = CuentaTesoreria::create([
            'Banco' => 'BBVA', 'NumeroCuenta' => '2', 'TipoCuenta' => 'BANCO',
            'SaldoActual' => 0, 'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);

        $movimiento = app(TesoreriaGerenciaService::class)->transferir([
            'CuentaOrigen' => $origen->CuentaTesoreriaID,
            'CuentaDestino' => $destino->CuentaTesoreriaID,
            'Monto' => 100,
            'FechaContable' => '2026-10-12',
            'Concepto' => 'Remesa de la apertura',
        ], 7);

        $this->assertSame(MovimientoTesoreria::TIPO_TRANSFERENCIA, $movimiento->Tipo);
        $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
        $this->assertSame('2026-10-12', $movimiento->FechaMovimiento->toDateString());
    }

    public function test_compra_y_gasto_usan_fecha_de_apertura_aunque_el_comprobante_sea_anterior(): void
    {
        $cuenta = CuentaTesoreria::create([
            'Banco' => 'BCP', 'NumeroCuenta' => '1', 'TipoCuenta' => 'BANCO',
            'SaldoActual' => 500, 'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);

        foreach ([
            [MovimientoTesoreria::COMPRA, 31, '2026-09-24'],
            [MovimientoTesoreria::GASTO, 32, '2026-09-22'],
        ] as [$tipo, $documentoId, $fechaEmision]) {
            $movimiento = app(TesoreriaGerenciaService::class)->registrarEgresoDocumento([
                'Origen' => $cuenta->CuentaTesoreriaID,
                'Monto' => 100,
                'FechaContable' => $fechaEmision,
                'TipoDocumento' => $tipo,
                'DocumentoID' => $documentoId,
                'Concepto' => 'Documento pagado',
            ], 7);

            $this->assertSame('2026-10-02', $movimiento->FechaContable->toDateString());
            $this->assertSame('2026-10-12', $movimiento->FechaMovimiento->toDateString());
        }

        $this->assertSame(300.0, (float) $cuenta->fresh()->SaldoActual);
    }

    public function test_correccion_de_compras_exige_dos_comprobantes_y_no_modifica_saldos(): void
    {
        DB::table('users')->insert(['id' => 7]);
        DB::table('Compra')->insert([
            ['CompraID' => 31, 'SedeID' => 1, 'Numero' => 'FF02-00473810', 'FechaEmision' => '2026-09-24', 'FechaCreacion' => '2026-10-02', 'Total' => 351.88, 'TipoCompra' => 'CONTADO', 'EstadoPago' => 'PAGADO', 'Activo' => 1, 'OrigenTesoreriaTipo' => 'CAJA_GERENCIA', 'CuentaTesoreriaID' => null],
            ['CompraID' => 32, 'SedeID' => 1, 'Numero' => 'S002-235430331', 'FechaEmision' => '2026-09-22', 'FechaCreacion' => '2026-10-02', 'Total' => 590.08, 'TipoCompra' => 'CONTADO', 'EstadoPago' => 'PAGADO', 'Activo' => 1, 'OrigenTesoreriaTipo' => 'CUENTA_BANCARIA', 'CuentaTesoreriaID' => 5],
        ]);
        foreach ([
            [31, 351.88, '2026-09-24', 'CAJA_GERENCIA', null],
            [32, 590.08, '2026-09-22', 'CUENTA_BANCARIA', 5],
        ] as [$id, $monto, $fecha, $origen, $cuentaId]) {
            DB::table('tesoreria_movimientos')->insert([
                'Tipo' => 'EGRESO_COMPRA', 'OrigenTipo' => $origen, 'CuentaOrigenID' => $cuentaId,
                'CuentaOrigenNombre' => 'Origen', 'DestinoTipo' => 'COMPRA', 'CuentaDestinoNombre' => 'Compra',
                'Monto' => $monto, 'FechaContable' => $fecha, 'FechaMovimiento' => '2026-10-12 10:30:00',
                'Concepto' => 'Compra', 'UsuarioID' => 7, 'CompraID' => $id,
                'SaldoAnteriorOrigen' => 1000, 'SaldoNuevoOrigen' => 1000 - $monto,
            ]);
        }

        $opciones = ['--compra' => [31, 32]];
        $this->artisan('gerencia:corregir-fechas-compras-octubre-2026', $opciones)->assertExitCode(0);
        $this->assertSame(0, DB::table('tesoreria_movimientos')->where('FechaContable', '2026-10-02')->count());

        $this->artisan('gerencia:corregir-fechas-compras-octubre-2026', $opciones + [
            '--aplicar' => true, '--usuario-id' => 7, '--confirmar' => 'APLICAR-COMPRAS-GERENCIA-2026-10-02',
        ])->assertExitCode(0);

        $this->assertSame(2, DB::table('tesoreria_movimientos')->where('FechaContable', '2026-10-02')->count());
        $this->assertSame(2, DB::table('tesoreria_movimientos')->where('FechaMovimiento', '2026-10-12 10:30:00')->count());
        $this->assertSame(2, DB::table('logs')->where('modelo', 'MovimientoTesoreria')->count());
        $this->assertEquals(351.88, DB::table('Compra')->where('CompraID', 31)->value('Total'));
        $this->assertEquals(590.08, DB::table('Compra')->where('CompraID', 32)->value('Total'));

        $this->artisan('gerencia:corregir-fechas-compras-octubre-2026', $opciones + [
            '--aplicar' => true, '--usuario-id' => 7, '--confirmar' => 'APLICAR-COMPRAS-GERENCIA-2026-10-02',
        ])->assertExitCode(0);
        $this->assertSame(2, DB::table('logs')->count());
    }

    public function test_correccion_rechaza_compras_que_no_corresponden_a_los_importes_reportados(): void
    {
        DB::table('Compra')->insert([
            ['CompraID' => 31, 'SedeID' => 1, 'Numero' => 'A', 'FechaEmision' => '2026-09-24', 'FechaCreacion' => '2026-10-02', 'Total' => 351.88, 'TipoCompra' => 'CONTADO', 'EstadoPago' => 'PAGADO', 'Activo' => 1, 'OrigenTesoreriaTipo' => 'CAJA_GERENCIA', 'CuentaTesoreriaID' => null],
            ['CompraID' => 32, 'SedeID' => 1, 'Numero' => 'B', 'FechaEmision' => '2026-09-22', 'FechaCreacion' => '2026-10-02', 'Total' => 590.09, 'TipoCompra' => 'CONTADO', 'EstadoPago' => 'PAGADO', 'Activo' => 1, 'OrigenTesoreriaTipo' => 'CAJA_GERENCIA', 'CuentaTesoreriaID' => null],
        ]);
        foreach ([31 => ['2026-09-24', 351.88], 32 => ['2026-09-22', 590.09]] as $id => [$fecha, $monto]) {
            DB::table('tesoreria_movimientos')->insert([
                'Tipo' => 'EGRESO_COMPRA', 'OrigenTipo' => 'CAJA_GERENCIA', 'CuentaOrigenNombre' => 'Caja',
                'DestinoTipo' => 'COMPRA', 'CuentaDestinoNombre' => 'Compra', 'Monto' => $monto,
                'FechaContable' => $fecha, 'FechaMovimiento' => '2026-10-12 10:30:00',
                'Concepto' => 'Compra', 'UsuarioID' => 7, 'CompraID' => $id,
                'SaldoAnteriorOrigen' => 1000, 'SaldoNuevoOrigen' => 1000 - $monto,
            ]);
        }

        $this->artisan('gerencia:corregir-fechas-compras-octubre-2026', ['--compra' => [31, 32]])
            ->assertExitCode(1);
        $this->assertSame(0, DB::table('tesoreria_movimientos')->where('FechaContable', '2026-10-02')->count());
    }
}
