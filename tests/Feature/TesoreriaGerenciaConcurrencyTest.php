<?php

namespace Tests\Feature;

use App\Models\AperturaCierreDia;
use App\Models\Compra;
use App\Models\CuentaTesoreria;
use App\Models\MovimientoTesoreria;
use App\Services\TesoreriaGerenciaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class TesoreriaGerenciaConcurrencyTest extends TestCase
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
        Schema::create('tesoreria_cuentas', function (Blueprint $table): void {
            $table->id('CuentaTesoreriaID');
            $table->string('Banco');
            $table->string('NumeroCuenta')->unique();
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
            $table->unsignedBigInteger('MovimientoOriginalID')->nullable()->unique();
            $table->unsignedBigInteger('CompraID')->nullable();
            $table->unsignedBigInteger('GastoID')->nullable();
            $table->decimal('SaldoAnteriorOrigen', 14, 2)->nullable();
            $table->decimal('SaldoNuevoOrigen', 14, 2)->nullable();
            $table->decimal('SaldoAnteriorDestino', 14, 2)->nullable();
            $table->decimal('SaldoNuevoDestino', 14, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('Compra', function (Blueprint $table): void {
            $table->id('CompraID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Numero');
            $table->dateTime('FechaEmision');
            $table->dateTime('FechaCreacion');
            $table->dateTime('FechaModificacion')->nullable();
            $table->dateTime('FechaPago')->nullable();
            $table->unsignedBigInteger('UsuarioPagoID')->nullable();
            $table->decimal('Total', 14, 2);
            $table->string('TipoCompra');
            $table->string('EstadoPago');
            $table->boolean('Activo');
            $table->string('OrigenTesoreriaTipo')->nullable();
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable();
        });
        Schema::create('logs', function (Blueprint $table): void {
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
        DB::table('Sede')->insert(['SedeID' => 1, 'Nombre' => 'Gerencia']);
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

    public function test_factura_pendiente_no_se_puede_pagar_dos_veces_con_la_instancia_obsoleta(): void
    {
        $cuenta = $this->crearCuenta('BCP', '123', 10000);
        $compra = $this->crearCompraCredito(500);
        $instanciaObsoleta = Compra::query()->findOrFail($compra->CompraID);
        $service = app(TesoreriaGerenciaService::class);

        $service->pagarCompraCredito($instanciaObsoleta, $cuenta->CuentaTesoreriaID, 7);

        try {
            $service->pagarCompraCredito($instanciaObsoleta, $cuenta->CuentaTesoreriaID, 8);
            $this->fail('El segundo intento debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ya no está pendiente', $exception->errors()['factura'][0]);
        }

        $this->assertSame(1, DB::table('tesoreria_movimientos')->where('Tipo', 'EGRESO_COMPRA')->count());
        $this->assertEquals(9500.00, (float) $cuenta->fresh()->SaldoActual);
        $this->assertSame('PAGADO', DB::table('Compra')->where('CompraID', $compra->CompraID)->value('EstadoPago'));
        $this->assertSame('2026-10-02', substr(DB::table('Compra')->where('CompraID', $compra->CompraID)->value('FechaPago'), 0, 10));
    }

    public function test_inactivar_cuenta_consulta_el_saldo_actual_bajo_bloqueo(): void
    {
        $cuentaObsoleta = $this->crearCuenta('BCP', '456', 0);
        DB::table('tesoreria_cuentas')
            ->where('CuentaTesoreriaID', $cuentaObsoleta->CuentaTesoreriaID)
            ->update(['SaldoActual' => 5000]);

        try {
            app(TesoreriaGerenciaService::class)->actualizarCuenta($cuentaObsoleta, [
                'Banco' => 'BCP',
                'NumeroCuenta' => '456',
                'Estado' => CuentaTesoreria::ESTADO_INACTIVA,
            ]);
            $this->fail('No debe inactivarse una cuenta que ahora tiene saldo.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('saldo distinto de cero', $exception->errors()['Estado'][0]);
        }

        $cuentaActual = CuentaTesoreria::query()->findOrFail($cuentaObsoleta->CuentaTesoreriaID);
        $this->assertSame(CuentaTesoreria::ESTADO_ACTIVA, $cuentaActual->Estado);
        $this->assertEquals(5000.00, (float) $cuentaActual->SaldoActual);
    }

    public function test_transferencias_normales_rechazan_cuentas_inactivas_en_origen_y_destino(): void
    {
        $origen = $this->crearCuenta('BCP', '111', 1000);
        $destino = $this->crearCuenta('BBVA', '222', 0);
        DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', $destino->CuentaTesoreriaID)
            ->update(['Estado' => CuentaTesoreria::ESTADO_INACTIVA]);

        $this->assertTransferenciaRechazada($origen->CuentaTesoreriaID, $destino->CuentaTesoreriaID);

        DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', $origen->CuentaTesoreriaID)
            ->update(['Estado' => CuentaTesoreria::ESTADO_INACTIVA]);
        DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', $destino->CuentaTesoreriaID)
            ->update(['Estado' => CuentaTesoreria::ESTADO_ACTIVA]);

        $this->assertTransferenciaRechazada($origen->CuentaTesoreriaID, $destino->CuentaTesoreriaID);
        $this->assertSame(0, DB::table('tesoreria_movimientos')->count());
    }

    public function test_egresos_de_compra_y_gasto_rechazan_una_cuenta_inactiva(): void
    {
        $cuenta = $this->crearCuenta('BCP', '333', 1000);
        DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', $cuenta->CuentaTesoreriaID)
            ->update(['Estado' => CuentaTesoreria::ESTADO_INACTIVA]);
        $service = app(TesoreriaGerenciaService::class);

        foreach ([MovimientoTesoreria::COMPRA, MovimientoTesoreria::GASTO] as $tipo) {
            try {
                $service->registrarEgresoDocumento([
                    'Origen' => $cuenta->CuentaTesoreriaID,
                    'Monto' => 100,
                    'FechaContable' => '2026-10-02',
                    'TipoDocumento' => $tipo,
                    'DocumentoID' => 1,
                    'Concepto' => 'Prueba cuenta inactiva',
                ], 7);
                $this->fail('No se debe aceptar un egreso desde una cuenta inactiva.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('no esta activa', $exception->errors()['CuentaOrigen'][0]);
            }
        }

        $this->assertSame(0, DB::table('tesoreria_movimientos')->count());
        $this->assertEquals(1000.00, (float) $cuenta->fresh()->SaldoActual);
    }

    public function test_extorno_historico_devuelve_dinero_a_cuenta_que_ahora_esta_inactiva(): void
    {
        $origen = $this->crearCuenta('BCP', '444', 1000);
        $destino = $this->crearCuenta('BBVA', '555', 0);
        $service = app(TesoreriaGerenciaService::class);
        $transferencia = $service->transferir([
            'CuentaOrigen' => $origen->CuentaTesoreriaID,
            'CuentaDestino' => $destino->CuentaTesoreriaID,
            'Monto' => 100,
            'FechaContable' => '2026-10-02',
            'Concepto' => 'Transferencia histórica',
        ], 7);
        DB::table('tesoreria_cuentas')->where('CuentaTesoreriaID', $destino->CuentaTesoreriaID)
            ->update(['Estado' => CuentaTesoreria::ESTADO_INACTIVA]);

        $extorno = $service->extornar($transferencia, ['Concepto' => 'Extorno histórico'], 7);

        $this->assertSame(MovimientoTesoreria::TIPO_EXTORNO, $extorno->Tipo);
        $this->assertSame($transferencia->MovimientoTesoreriaID, $extorno->MovimientoOriginalID);
        $this->assertSame(CuentaTesoreria::ESTADO_INACTIVA, $destino->fresh()->Estado);
        $this->assertEquals(0.00, (float) $destino->fresh()->SaldoActual);
        $this->assertEquals(1000.00, (float) $origen->fresh()->SaldoActual);
    }

    private function crearCuenta(string $banco, string $numero, float $saldo): CuentaTesoreria
    {
        return CuentaTesoreria::create([
            'Banco' => $banco,
            'NumeroCuenta' => $numero,
            'TipoCuenta' => 'BANCO',
            'SaldoActual' => $saldo,
            'Estado' => CuentaTesoreria::ESTADO_ACTIVA,
        ]);
    }

    private function crearCompraCredito(float $total): Compra
    {
        $id = DB::table('Compra')->insertGetId([
            'SedeID' => 1,
            'Numero' => 'F-001',
            'FechaEmision' => '2026-09-01 00:00:00',
            'FechaCreacion' => '2026-10-02 09:00:00',
            'Total' => $total,
            'TipoCompra' => 'CREDITO',
            'EstadoPago' => 'PENDIENTE',
            'Activo' => true,
        ], 'CompraID');

        return Compra::query()->findOrFail($id);
    }

    private function assertTransferenciaRechazada(int $origenId, int $destinoId): void
    {
        try {
            app(TesoreriaGerenciaService::class)->transferir([
                'CuentaOrigen' => $origenId,
                'CuentaDestino' => $destinoId,
                'Monto' => 100,
                'FechaContable' => '2026-10-02',
                'Concepto' => 'No debe registrarse',
            ], 7);
            $this->fail('La transferencia debía rechazarse por cuenta inactiva.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
