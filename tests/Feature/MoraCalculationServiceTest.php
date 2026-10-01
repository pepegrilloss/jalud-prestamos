<?php

namespace Tests\Feature;

use App\Jobs\CalcularMoraAutomatica;
use App\Models\Credito;
use App\Services\MoraCalculationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MoraCalculationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        Schema::create('Credito', function (Blueprint $table) {
            $table->increments('CreditoID');
            $table->unsignedInteger('ProposicionCreditoID');
            $table->dateTime('FechaGeneracion')->nullable();
            $table->date('FechaInicio')->nullable();
            $table->date('FechaVencimiento');
            $table->boolean('Activo')->default(true);
            $table->string('EstatusCreditoFinal')->default('ACTIVO');
            $table->unsignedInteger('SedeID');
        });

        Schema::create('Sede', function (Blueprint $table) {
            $table->increments('SedeID');
            $table->string('Nombre');
            $table->boolean('Activo')->default(true);
        });

        DB::table('Sede')->insert([
            ['SedeID' => 1, 'Nombre' => 'Chiclayo', 'Activo' => true],
            ['SedeID' => 2, 'Nombre' => 'Trujillo', 'Activo' => true],
            ['SedeID' => 3, 'Nombre' => 'Gerencia', 'Activo' => true],
        ]);

        Schema::create('ProposicionCredito', function (Blueprint $table) {
            $table->increments('ProposicionCreditoID');
            $table->unsignedInteger('ClienteID');
            $table->string('CodigoCredito');
            $table->decimal('MontoTotalPagar', 12, 2);
            $table->decimal('SaldoPendiente', 12, 2);
            $table->decimal('TasaMora', 8, 2)->nullable();
            $table->boolean('Activo')->default(true);
            $table->boolean('Eliminado')->default(false);
            $table->unsignedInteger('SedeID');
        });

        Schema::create('Cliente', function (Blueprint $table) {
            $table->increments('ClienteID');
            $table->unsignedInteger('TasaMoraID')->nullable();
            $table->unsignedInteger('SedeID');
        });

        Schema::create('TasaMora', function (Blueprint $table) {
            $table->increments('TasaMoraID');
            $table->decimal('Porcentaje', 8, 2);
        });

        Schema::create('pago', function (Blueprint $table) {
            $table->increments('PagoID');
            $table->unsignedInteger('CreditoID');
            $table->decimal('MontoPagado', 12, 2);
            $table->dateTime('FechaPago');
            $table->boolean('Activo')->default(true);
            $table->boolean('EsMora')->default(false);
        });

        Schema::create('mora', function (Blueprint $table) {
            $table->increments('MoraID');
            $table->unsignedInteger('CreditoID');
            $table->date('FechaMora');
            $table->decimal('SaldoPendiente', 12, 2);
            $table->decimal('PorcentajeMora', 8, 2);
            $table->decimal('MontoMora', 12, 2);
            $table->decimal('MoraAcumulada', 12, 2);
            $table->unsignedInteger('SedeID');
            $table->timestamps();
        });

        Schema::create('solicitudes_resolucion_excedente', function (Blueprint $table) {
            $table->increments('SolicitudID');
            $table->unsignedInteger('PagoOrigenID')->nullable();
            $table->decimal('MontoAplicar', 12, 2);
            $table->string('TipoResolucion');
            $table->string('Estado');
            $table->dateTime('FechaCierre')->nullable();
            $table->timestamps();
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('accion')->nullable();
            $table->string('modelo')->nullable();
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
    }

    public function test_genera_mora_el_domingo_posterior_al_vencimiento(): void
    {
        $credito = $this->crearCredito(
            vencimiento: '2026-08-22',
            total: 8400,
            saldo: 2520,
            tasaCredito: 0.50,
            tasaCliente: 0.50,
        );
        $this->crearPago($credito->CreditoID, 5880, '2026-08-22 13:14:31');

        $resultado = app(MoraCalculationService::class)
            ->procesarCreditoHasta($credito, '2026-08-23');

        $this->assertSame(1, $resultado['creadas']);
        $this->assertDatabaseHas('mora', [
            'CreditoID' => $credito->CreditoID,
            'SaldoPendiente' => 2520,
            'PorcentajeMora' => 0.50,
            'MontoMora' => 12.60,
            'MoraAcumulada' => 12.60,
        ]);
        $this->assertSame('2026-08-23', substr((string) DB::table('mora')->value('FechaMora'), 0, 10));
    }

    public function test_usa_la_tasa_guardada_en_el_credito_si_el_cliente_no_tiene_tasa(): void
    {
        $credito = $this->crearCredito(
            vencimiento: '2026-08-12',
            total: 896.40,
            saldo: 692.40,
            tasaCredito: 0.50,
            tasaCliente: null,
        );
        $this->crearPago($credito->CreditoID, 110, '2026-08-12 17:03:00');

        $resultado = app(MoraCalculationService::class)
            ->procesarCreditoHasta($credito, '2026-08-13');

        $this->assertSame(1, $resultado['creadas']);
        $this->assertDatabaseHas('mora', [
            'CreditoID' => $credito->CreditoID,
            'SaldoPendiente' => 786.40,
            'MontoMora' => 3.93,
        ]);
    }

    public function test_reconstruye_el_saldo_de_cada_dia_y_no_duplica_moras(): void
    {
        $credito = $this->crearCredito(
            vencimiento: '2026-08-10',
            total: 1000,
            saldo: 900,
            tasaCredito: 1,
            tasaCliente: null,
        );
        $this->crearPago($credito->CreditoID, 100, '2026-08-11 15:00:00');

        $servicio = app(MoraCalculationService::class);
        $primero = $servicio->procesarCreditoHasta($credito, '2026-08-12');
        $segundo = $servicio->procesarCreditoHasta($credito, '2026-08-12');

        $this->assertSame(2, $primero['creadas']);
        $this->assertSame(0, $segundo['creadas']);
        $this->assertSame(2, DB::table('mora')->where('CreditoID', $credito->CreditoID)->count());

        $moras = DB::table('mora')->where('CreditoID', $credito->CreditoID)->orderBy('FechaMora')->get();
        $this->assertSame(1000.0, (float) $moras[0]->SaldoPendiente);
        $this->assertSame(10.0, (float) $moras[0]->MontoMora);
        $this->assertSame(900.0, (float) $moras[1]->SaldoPendiente);
        $this->assertSame(9.0, (float) $moras[1]->MontoMora);
        $this->assertSame(19.0, (float) $moras[1]->MoraAcumulada);
    }

    public function test_el_calculo_automatico_solo_procesa_la_sede_abierta(): void
    {
        $creditoChiclayo = $this->crearCredito('2026-08-22', 1000, 500, 0.5, null, 1);
        $this->crearCredito('2026-08-22', 1000, 500, 0.5, null, 2);

        $servicio = \Mockery::mock(MoraCalculationService::class);
        $servicio->shouldReceive('procesarCreditoHasta')
            ->once()
            ->withArgs(fn (Credito $credito) => $credito->CreditoID === $creditoChiclayo->CreditoID)
            ->andReturn(['creadas' => 0]);

        (new CalcularMoraAutomatica('2026-08-23', 1))->handle($servicio);
    }

    public function test_gerencia_no_ejecuta_calculo_automatico_de_mora(): void
    {
        $servicio = \Mockery::mock(MoraCalculationService::class);
        $servicio->shouldNotReceive('procesarCreditoHasta');

        (new CalcularMoraAutomatica('2026-08-23', 3))->handle($servicio);
    }

    private function crearCredito(
        string $vencimiento,
        float $total,
        float $saldo,
        ?float $tasaCredito,
        ?float $tasaCliente,
        int $sedeId = 2,
    ): Credito {
        $tasaMoraId = null;
        if ($tasaCliente !== null) {
            $tasaMoraId = DB::table('TasaMora')->insertGetId(['Porcentaje' => $tasaCliente]);
        }

        $clienteId = DB::table('Cliente')->insertGetId([
            'TasaMoraID' => $tasaMoraId,
            'SedeID' => $sedeId,
        ]);

        $proposicionId = DB::table('ProposicionCredito')->insertGetId([
            'ClienteID' => $clienteId,
            'CodigoCredito' => 'C-PRUEBA',
            'MontoTotalPagar' => $total,
            'SaldoPendiente' => $saldo,
            'TasaMora' => $tasaCredito,
            'Activo' => true,
            'Eliminado' => false,
            'SedeID' => $sedeId,
        ]);

        $creditoId = DB::table('Credito')->insertGetId([
            'ProposicionCreditoID' => $proposicionId,
            'FechaGeneracion' => '2026-07-24 10:07:35',
            'FechaInicio' => '2026-07-25',
            'FechaVencimiento' => $vencimiento,
            'Activo' => true,
            'EstatusCreditoFinal' => 'ACTIVO',
            'SedeID' => $sedeId,
        ]);

        return Credito::withoutGlobalScope('sede')->findOrFail($creditoId);
    }

    private function crearPago(int $creditoId, float $monto, string $fecha): void
    {
        DB::table('pago')->insert([
            'CreditoID' => $creditoId,
            'MontoPagado' => $monto,
            'FechaPago' => $fecha,
            'Activo' => true,
            'EsMora' => false,
        ]);
    }
}
