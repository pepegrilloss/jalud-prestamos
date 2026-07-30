<?php

namespace Tests\Feature;

use App\Models\AperturaCierreDia;
use App\Models\FondoSede;
use App\Models\MovimientoFondo;
use App\Models\TransferenciaSede;
use App\Services\FondoSedeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class FondoSedeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        $this->crearEsquemaMinimo();

        foreach ([AperturaCierreDia::class, FondoSede::class, MovimientoFondo::class, TransferenciaSede::class] as $modelo) {
            $modelo::flushEventListeners();
        }
    }

    public function test_aceptar_solicitud_de_gerencia_descuenta_la_sede_solicitada_y_abona_gerencia(): void
    {
        $gerenciaId = DB::table('Sede')->insertGetId(['Nombre' => 'Gerencia', 'Activo' => true]);
        $trujilloId = DB::table('Sede')->insertGetId(['Nombre' => 'Trujillo', 'Activo' => true]);

        DB::table('apertura_cierre_dia')->insert([
            'Fecha' => '2026-07-30',
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => '2026-07-30 08:00:00',
        ]);

        $fondoGerencia = FondoSede::create(['SedeID' => $gerenciaId, 'Saldo' => 100]);
        $fondoTrujillo = FondoSede::create(['SedeID' => $trujilloId, 'Saldo' => 500]);

        $transferencia = TransferenciaSede::create([
            'SedeOrigenID' => $gerenciaId,
            'SedeDestinoID' => $trujilloId,
            'CuentaOrigen' => 'CAJA_ABIERTA',
            'CuentaDestino' => 'CAJA_ABIERTA',
            'EsSolicitudCapital' => false,
            'EsSolicitudGerencia' => true,
            'UsuarioOrigenID' => 13,
            'Monto' => 200,
            'Estado' => 'PENDIENTE',
            'Observacion' => 'Gerencia solicita a Trujillo',
            'FechaTransferencia' => '2026-07-30 09:00:00',
        ]);

        app(FondoSedeService::class)->aceptarTransferencia($transferencia, 13);

        $this->assertSame(300.0, (float) $fondoTrujillo->fresh()->Saldo);
        $this->assertSame(300.0, (float) $fondoGerencia->fresh()->Saldo);
        $this->assertSame('ACEPTADO', $transferencia->fresh()->Estado);
        $this->assertSame(200.0, (float) $transferencia->fresh()->MontoAprobado);
        $this->assertDatabaseHas('movimientos_fondo', [
            'SedeID' => $trujilloId,
            'Tipo' => 'ENVIO_TRANSFERENCIA',
            'Monto' => -200,
        ]);
        $this->assertDatabaseHas('movimientos_fondo', [
            'SedeID' => $gerenciaId,
            'Tipo' => 'RECEPCION_TRANSFERENCIA',
            'Monto' => 200,
        ]);
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::dropAllTables();

        Schema::create('Sede', function (Blueprint $table) {
            $table->id('SedeID');
            $table->string('Nombre');
            $table->boolean('Activo')->default(true);
        });

        Schema::create('apertura_cierre_dia', function (Blueprint $table) {
            $table->id('AperturaCierreDiaID');
            $table->date('Fecha');
            $table->string('EstadoDia', 20);
            $table->timestamp('FechaApertura')->nullable();
            $table->timestamp('FechaCierre')->nullable();
            $table->unsignedBigInteger('UsuarioAperturaID')->nullable();
            $table->unsignedBigInteger('UsuarioCierreID')->nullable();
            $table->string('Observaciones')->nullable();
            $table->unsignedBigInteger('SedeID')->nullable();
            $table->boolean('pagos_promotor_bloqueados')->default(false);
            $table->timestamps();
        });

        Schema::create('fondo_sedes', function (Blueprint $table) {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID')->unique();
            $table->decimal('Saldo', 14, 2)->default(0);
            $table->decimal('SaldoCajaChica', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transferencia_sedes', function (Blueprint $table) {
            $table->id('TransferenciaID');
            $table->unsignedBigInteger('SedeOrigenID');
            $table->unsignedBigInteger('SedeDestinoID');
            $table->string('CuentaOrigen', 30)->default('CAJA_ABIERTA');
            $table->string('CuentaDestino', 30)->default('CAJA_ABIERTA');
            $table->boolean('EsSolicitudCapital')->default(false);
            $table->boolean('EsSolicitudGerencia')->default(false);
            $table->unsignedBigInteger('UsuarioOrigenID');
            $table->unsignedBigInteger('UsuarioRespondeID')->nullable();
            $table->decimal('Monto', 14, 2);
            $table->decimal('MontoAprobado', 14, 2)->nullable();
            $table->string('Estado', 20)->default('PENDIENTE');
            $table->string('Observacion', 500)->nullable();
            $table->timestamp('FechaTransferencia')->nullable();
            $table->timestamp('FechaRespuesta')->nullable();
            $table->string('VoucherImagen', 500)->nullable();
            $table->timestamp('FechaCierre')->nullable();
            $table->timestamps();
        });

        Schema::create('movimientos_fondo', function (Blueprint $table) {
            $table->id('MovimientoID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Tipo', 50);
            $table->decimal('Monto', 14, 2);
            $table->decimal('SaldoAnterior', 14, 2)->default(0);
            $table->decimal('SaldoNuevo', 14, 2)->default(0);
            $table->unsignedBigInteger('TransferenciaID')->nullable();
            $table->unsignedBigInteger('UsuarioID');
            $table->string('Observacion', 500)->nullable();
            $table->timestamp('FechaMovimiento')->nullable();
            $table->string('VoucherImagen', 500)->nullable();
            $table->timestamps();
        });
    }
}
