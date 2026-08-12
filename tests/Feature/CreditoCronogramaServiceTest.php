<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Services\CalendarioLaboralService;
use App\Services\CreditoCronogramaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class CreditoCronogramaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extension pdo_sqlite no esta disponible en este entorno.');
        }

        Schema::create('Credito', function (Blueprint $table) {
            $table->increments('CreditoID');
            $table->unsignedInteger('SedeID')->nullable();
            $table->dateTime('FechaGeneracion');
            $table->date('FechaInicio')->nullable();
            $table->date('FechaVencimiento')->nullable();
        });

        Schema::create('cuota', function (Blueprint $table) {
            $table->increments('CuotaID');
            $table->unsignedInteger('CreditoID');
            $table->integer('NumeroCuota');
            $table->date('FechaVencimiento');
            $table->decimal('MontoCuota', 12, 2);
            $table->string('Estado', 20)->default('NORMAL');
            $table->integer('DiasAtraso')->default(0);
            $table->decimal('MontoMora', 12, 2)->default(0);
            $table->dateTime('FechaPago')->nullable();
            $table->dateTime('FechaCreacion')->nullable();
            $table->dateTime('FechaModificacion')->nullable();
            $table->date('FechaCierre')->nullable();
            $table->boolean('Activo')->default(true);
            $table->unsignedInteger('SedeID')->nullable();
        });

        Schema::create('pago', function (Blueprint $table) {
            $table->increments('PagoID');
            $table->unsignedInteger('CuotaID')->nullable();
        });

        $this->setCalendarCache('feriadosNagerPorAnio', [2026 => []]);
        $this->setCalendarCache('reglasLocales', []);
    }

    protected function tearDown(): void
    {
        $this->setCalendarCache('feriadosNagerPorAnio', []);
        $this->setCalendarCache('reglasLocales', null);

        parent::tearDown();
    }

    public function test_filas_cero_no_reducen_las_cuotas_numeradas(): void
    {
        $credito = $this->crearCredito();

        foreach (range(1, 21) as $numero) {
            $this->crearCuota($credito->CreditoID, $numero, "2026-08-{$numero}");
        }
        foreach (range(1, 9) as $dia) {
            $this->crearCuota($credito->CreditoID, 0, "2026-07-{$dia}", 0);
        }

        CreditoCronogramaService::sincronizarCuotasNumeradas($credito, 21, 15);

        $this->assertSame(21, DB::table('cuota')->where('NumeroCuota', '>', 0)->where('Activo', 1)->count());
        $this->assertSame(range(1, 21), DB::table('cuota')->where('NumeroCuota', '>', 0)->where('Activo', 1)->orderBy('NumeroCuota')->pluck('NumeroCuota')->all());
        $this->assertSame(9, DB::table('cuota')->where('NumeroCuota', 0)->count());
    }

    public function test_cuota_sobrante_con_pago_se_conserva_inactiva(): void
    {
        $credito = $this->crearCredito();

        foreach (range(1, 5) as $numero) {
            $this->crearCuota($credito->CreditoID, $numero, "2026-08-0{$numero}");
        }

        $cuotaConPago = DB::table('cuota')->where('NumeroCuota', 5)->value('CuotaID');
        DB::table('pago')->insert(['CuotaID' => $cuotaConPago]);

        CreditoCronogramaService::sincronizarCuotasNumeradas($credito, 3, 10);

        $this->assertDatabaseHas('pago', ['CuotaID' => $cuotaConPago]);
        $this->assertDatabaseHas('cuota', ['CuotaID' => $cuotaConPago, 'Activo' => 0]);
        $this->assertDatabaseMissing('cuota', ['CreditoID' => $credito->CreditoID, 'NumeroCuota' => 4]);
        $this->assertSame(3, DB::table('cuota')->where('NumeroCuota', '>', 0)->where('Activo', 1)->count());
    }

    private function crearCredito(): Credito
    {
        $id = DB::table('Credito')->insertGetId([
            'SedeID' => 1,
            'FechaGeneracion' => '2026-07-24 10:00:00',
        ]);

        return Credito::withoutGlobalScope('sede')->findOrFail($id);
    }

    private function crearCuota(int $creditoId, int $numero, string $fecha, float $monto = 15): void
    {
        DB::table('cuota')->insert([
            'CreditoID' => $creditoId,
            'NumeroCuota' => $numero,
            'FechaVencimiento' => $fecha,
            'MontoCuota' => $monto,
            'Estado' => $numero > 0 ? 'NORMAL' : 'FERIADO',
            'Activo' => true,
            'SedeID' => 1,
        ]);
    }

    private function setCalendarCache(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(CalendarioLaboralService::class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
