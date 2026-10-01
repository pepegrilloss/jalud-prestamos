<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Services\CreditoHistoricoService;
use App\Services\CreditoCronogramaService;
use App\Services\SaldoPendienteService;
use App\Models\ProposicionCredito;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditoHistoricoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        Schema::create('Sede', function (Blueprint $table) {
            $table->increments('SedeID');
            $table->string('Nombre');
            $table->boolean('Activo')->default(true);
        });
        Schema::create('Cliente', function (Blueprint $table) {
            $table->increments('ClienteID');
            $table->unsignedInteger('SedeID');
        });
        Schema::create('TipoPago', function (Blueprint $table) {
            $table->increments('TipoPagoID');
            $table->string('Nombre');
            $table->boolean('Activo')->default(true);
            $table->unsignedInteger('SedeID')->nullable();
        });
        Schema::create('ProposicionCredito', function (Blueprint $table) {
            $table->increments('ProposicionCreditoID');
            $table->unsignedInteger('ClienteID');
            $table->string('CodigoCredito');
            $table->decimal('MontoTotal', 12, 2);
            $table->decimal('TasaInteres', 8, 2)->default(0);
            $table->unsignedInteger('NumeroCuotas')->default(1);
            $table->decimal('MontoCuota', 12, 2)->default(0);
            $table->decimal('MontoInteres', 12, 2)->default(0);
            $table->decimal('MontoTotalPagar', 12, 2);
            $table->decimal('SaldoPendiente', 12, 2);
            $table->string('Estado');
            $table->boolean('Activo')->default(true);
            $table->date('FechaCierre')->nullable();
            $table->boolean('Eliminado')->default(false);
            $table->unsignedInteger('SedeID');
        });
        Schema::create('Credito', function (Blueprint $table) {
            $table->increments('CreditoID');
            $table->unsignedInteger('ProposicionCreditoID');
            $table->unsignedInteger('TipoPagoID');
            $table->text('ComentarioGeneracion')->nullable();
            $table->dateTime('FechaGeneracion');
            $table->date('FechaInicio')->nullable();
            $table->date('FechaVencimiento')->nullable();
            $table->unsignedInteger('UserGeneracionID');
            $table->boolean('Activo')->default(true);
            $table->date('FechaCierre')->nullable();
            $table->string('EstatusCreditoFinal')->default('ACTIVO');
            $table->dateTime('FechaSaldamiento')->nullable();
            $table->unsignedInteger('SedeID');
            $table->boolean('EsMigracionHistorica')->default(false);
        });
        Schema::create('cuota', function (Blueprint $table) {
            $table->increments('CuotaID');
            $table->unsignedInteger('CreditoID');
        });
        Schema::create('pago', function (Blueprint $table) {
            $table->increments('PagoID');
            $table->unsignedInteger('CreditoID');
        });
        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->default(0);
            $table->string('accion');
            $table->string('modelo');
            $table->unsignedInteger('modelo_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('platform')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->unsignedInteger('SedeID')->nullable();
        });

        DB::table('Sede')->insert(['SedeID' => 1, 'Nombre' => 'Chiclayo', 'Activo' => true]);
        DB::table('Cliente')->insert(['ClienteID' => 10, 'SedeID' => 1]);
        DB::table('TipoPago')->insert(['TipoPagoID' => 2, 'Nombre' => 'Diario', 'Activo' => true]);
        DB::table('ProposicionCredito')->insert([
            'ProposicionCreditoID' => 30,
            'ClienteID' => 10,
            'CodigoCredito' => 'C-000030',
            'MontoTotal' => 100000,
            'TasaInteres' => 0,
            'NumeroCuotas' => 32,
            'MontoCuota' => 3125,
            'MontoInteres' => 0,
            'MontoTotalPagar' => 100000,
            'SaldoPendiente' => 100000,
            'Estado' => 'APROBADO',
            'Activo' => true,
            'Eliminado' => false,
            'SedeID' => 1,
        ]);
    }

    public function test_registra_credito_historico_saldado_sin_cuotas_pagos_ni_movimientos_de_caja(): void
    {
        $credito = app(CreditoHistoricoService::class)->registrar(
            30,
            [
                'TipoPagoID' => 2,
                'FechaGeneracionHistorica' => '2026-06-01',
                'FechaSaldamientoHistorica' => '2026-06-30',
                'ComentarioGeneracion' => 'Pagado fuera del sistema',
            ],
            7,
            1,
        );

        $this->assertInstanceOf(Credito::class, $credito);
        $this->assertTrue($credito->EsMigracionHistorica);
        $this->assertSame('SALDADO', $credito->EstatusCreditoFinal);
        $this->assertSame('2026-06-30', $credito->FechaSaldamiento->toDateString());
        $this->assertSame(0.0, (float) DB::table('ProposicionCredito')->where('ProposicionCreditoID', 30)->value('SaldoPendiente'));
        $this->assertSame(0, DB::table('cuota')->where('CreditoID', $credito->CreditoID)->count());
        $this->assertSame(0, DB::table('pago')->where('CreditoID', $credito->CreditoID)->count());
        $this->assertFalse(Schema::hasTable('MovimientoFondo'));

        $proposicion = ProposicionCredito::withoutGlobalScopes()->findOrFail(30);
        $proposicion->NumeroCuotas = 45;
        $proposicion->save();

        $resumenCronograma = CreditoCronogramaService::sincronizarCuotasNumeradas($credito, 45, 2500);
        $this->assertSame(0, $resumenCronograma['creadas']);
        $this->assertSame(0, DB::table('cuota')->where('CreditoID', $credito->CreditoID)->count());

        DB::table('ProposicionCredito')->where('ProposicionCreditoID', 30)->update(['SaldoPendiente' => 900]);
        DB::table('Credito')->where('CreditoID', $credito->CreditoID)->update([
            'EstatusCreditoFinal' => 'ACTIVO',
        ]);

        $this->assertSame(0.0, SaldoPendienteService::recalcular(30));
        $this->assertSame('SALDADO', DB::table('Credito')->where('CreditoID', $credito->CreditoID)->value('EstatusCreditoFinal'));
        $this->assertSame('2026-06-30', substr((string) DB::table('Credito')->where('CreditoID', $credito->CreditoID)->value('FechaSaldamiento'), 0, 10));
        $this->assertSame(0.0, (float) DB::table('ProposicionCredito')->where('ProposicionCreditoID', 30)->value('SaldoPendiente'));
    }

    public function test_no_permite_fecha_de_pago_anterior_al_desembolso(): void
    {
        try {
            app(CreditoHistoricoService::class)->registrar(
                30,
                [
                    'TipoPagoID' => 2,
                    'FechaGeneracionHistorica' => '2026-06-20',
                    'FechaSaldamientoHistorica' => '2026-06-19',
                ],
                7,
                1,
            );
            $this->fail('Se esperaba una validacion de fechas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('FechaSaldamientoHistorica', $exception->errors());
        }

        $this->assertSame(0, DB::table('Credito')->count());
    }

    public function test_no_permite_registrar_dos_veces_la_misma_proposicion(): void
    {
        $service = app(CreditoHistoricoService::class);
        $datos = [
            'TipoPagoID' => 2,
            'FechaGeneracionHistorica' => '2026-06-01',
            'FechaSaldamientoHistorica' => '2026-06-30',
        ];

        $service->registrar(30, $datos, 7, 1);

        $this->expectException(ValidationException::class);
        $service->registrar(30, $datos, 7, 1);
    }
}
