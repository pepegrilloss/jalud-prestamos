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
            $table->decimal('SaldoAnteriorOrigen', 14, 2);
            $table->decimal('SaldoNuevoOrigen', 14, 2);
            $table->decimal('SaldoAnteriorDestino', 14, 2);
            $table->decimal('SaldoNuevoDestino', 14, 2);
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
}
