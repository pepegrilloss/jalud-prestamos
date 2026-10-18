<?php

namespace Tests\Feature;

use App\Filament\Resources\GastoResource;
use App\Filament\Resources\GastoResource\Pages\CreateGasto;
use App\Models\Gasto;
use App\Models\User;
use App\Services\TesoreriaGerenciaService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GastoInsufficientBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');

        Schema::create('Sede', function (Blueprint $table): void {
            $table->id('SedeID');
            $table->string('Nombre');
        });
        Schema::create('apertura_cierre_dia', function (Blueprint $table): void {
            $table->id('AperturaCierreDiaID');
            $table->unsignedBigInteger('SedeID');
            $table->date('Fecha');
            $table->string('EstadoDia');
        });
        Schema::create('fondo_sedes', function (Blueprint $table): void {
            $table->id('FondoSedeID');
            $table->unsignedBigInteger('SedeID');
            $table->decimal('Saldo', 14, 2);
            $table->timestamps();
        });
        Schema::create('tesoreria_cuentas', function (Blueprint $table): void {
            $table->id('CuentaTesoreriaID');
            $table->string('Banco');
            $table->string('NumeroCuenta');
            $table->string('TipoCuenta');
            $table->string('Estado');
            $table->decimal('SaldoActual', 14, 2);
            $table->dateTime('FechaUltimoMovimiento')->nullable();
            $table->timestamps();
        });
        Schema::create('Gasto', function (Blueprint $table): void {
            $table->id('GastoID');
            $table->unsignedBigInteger('SedeID');
            $table->string('Numero');
            $table->string('MetodoGasto');
            $table->string('OrigenTesoreriaTipo');
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable();
            $table->unsignedBigInteger('UsuarioRegistro');
            $table->decimal('Total', 14, 2);
            $table->boolean('Activo');
            $table->dateTime('FechaCreacion');
            $table->dateTime('FechaModificacion');
        });
        Schema::create('GastoDetalle', function (Blueprint $table): void {
            $table->id('GastoDetalleID');
            $table->unsignedBigInteger('GastoID');
            $table->string('Descripcion');
            $table->decimal('Monto', 14, 2);
        });
        Schema::create('logs', function (Blueprint $table): void {
            $table->id();
            foreach (['user_id', 'modelo_id', 'SedeID'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            foreach (['accion', 'modelo', 'old_values', 'new_values', 'ip_address', 'user_agent', 'machine_name', 'platform'] as $column) {
                $table->text($column)->nullable();
            }
            $table->dateTime('created_at');
        });
        Schema::create('tesoreria_movimientos', function (Blueprint $table): void {
            $table->id('MovimientoTesoreriaID');
            foreach (['Tipo', 'OrigenTipo', 'CuentaOrigenNombre', 'DestinoTipo', 'CuentaDestinoNombre', 'Concepto'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['CuentaOrigenID', 'CuentaDestinoID', 'GastoID', 'UsuarioID'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            foreach (['Monto', 'SaldoAnteriorOrigen', 'SaldoNuevoOrigen'] as $column) {
                $table->decimal($column, 14, 2)->nullable();
            }
            $table->date('FechaContable');
            $table->dateTime('FechaMovimiento');
            $table->text('Observaciones')->nullable();
            $table->timestamps();
        });

        DB::table('Sede')->insert(['SedeID' => 1, 'Nombre' => 'Gerencia']);
        DB::table('apertura_cierre_dia')->insert(['SedeID' => 1, 'Fecha' => '2026-10-02', 'EstadoDia' => 'ABIERTO']);
        DB::table('fondo_sedes')->insert(['SedeID' => 1, 'Saldo' => 3000]);
        DB::table('tesoreria_cuentas')->insert([
            'Banco' => 'BCP', 'NumeroCuenta' => '123', 'TipoCuenta' => 'AHORROS',
            'Estado' => 'ACTIVA', 'SaldoActual' => 3000,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('gerencia'));
        session(['sede_activa' => 1]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'SedeID' => 1]);
        $user->shouldReceive('isPrivileged')->andReturn(true);
        $user->shouldReceive('getEffectiveSedeId')->andReturn(1);
        $this->actingAs($user);
        Gasto::resetSedeFilterCache();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        Gasto::resetSedeFilterCache();
        parent::tearDown();
    }

    public function test_insufficient_cash_can_be_submitted_twice_without_a_404(): void
    {
        $this->assertRejectedTwice([['Descripcion' => 'asdas', 'Monto' => 100000]]);
    }

    public function test_total_of_all_lines_is_rejected_without_partial_records(): void
    {
        $this->assertRejectedTwice([
            ['Descripcion' => 'adas', 'Monto' => 2499.97],
            ['Descripcion' => 'sssss', 'Monto' => 1000],
        ]);
    }

    public function test_insufficient_bank_balance_also_preserves_the_form(): void
    {
        $this->assertRejectedTwice([['Descripcion' => 'asdas', 'Monto' => 100000]], '1');
        $this->assertSame(3000.0, (float) DB::table('tesoreria_cuentas')->value('SaldoActual'));
    }

    public function test_user_can_correct_the_amount_and_save_once_after_rejection(): void
    {
        $page = Livewire::test(GastoBalanceForm::class)
            ->set('data', $this->formData([['Descripcion' => 'asdas', 'Monto' => 100000]]))
            ->call('create')
            ->assertHasErrors(['data.OrigenTesoreria']);

        $page->set('data.detalles', [
            'linea-1' => ['Descripcion' => 'adas', 'Monto' => 1000],
            'linea-2' => ['Descripcion' => 'sssss', 'Monto' => 500],
        ])->call('create')->assertHasNoErrors()->assertRedirect(GastoResource::getUrl('index'));

        $this->assertSame(1, DB::table('Gasto')->count());
        $this->assertSame(2, DB::table('GastoDetalle')->count());
        $this->assertSame(1, DB::table('tesoreria_movimientos')->count());
        $this->assertSame(1500.0, (float) DB::table('Gasto')->value('Total'));
        $this->assertSame(1500.0, (float) DB::table('fondo_sedes')->value('Saldo'));
    }

    private function assertRejectedTwice(array $lines, string $origin = TesoreriaGerenciaService::CAJA_GERENCIA_KEY): void
    {
        $data = $this->formData($lines, $origin);
        $page = Livewire::test(GastoBalanceForm::class)->set('data', $data);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $page->call('create')->assertStatus(200)->assertHasErrors(['data.OrigenTesoreria'])
                ->assertSee('Saldo insuficiente')->assertSee('3,000.00');
            $this->assertNull($page->get('record'));
            $this->assertSame($data, $page->get('data'));
            $this->assertSame(0, DB::table('Gasto')->count());
            $this->assertSame(0, DB::table('GastoDetalle')->count());
            $this->assertSame(0, DB::table('tesoreria_movimientos')->count());
            $this->assertSame(3000.0, (float) DB::table('fondo_sedes')->value('Saldo'));
            Notification::assertNotified('No se pudo registrar el gasto');
        }
    }

    private function formData(array $lines, string $origin = TesoreriaGerenciaService::CAJA_GERENCIA_KEY): array
    {
        return ['Numero' => '1000', 'OrigenTesoreria' => $origin, 'detalles' => $lines];
    }
}

class GastoBalanceForm extends CreateGasto
{
    public static function authorizeResourceAccess(): void
    {
    }

    protected function authorizeAccess(): void
    {
    }

    protected function getForms(): array
    {
        return ['form' => $this->makeForm()->model(Gasto::class)->statePath('data')->schema([
            TextInput::make('Numero')->required(),
            TextInput::make('OrigenTesoreria')->required(),
            Repeater::make('detalles')->relationship()->schema([
                TextInput::make('Descripcion')->required(),
                TextInput::make('Monto')->numeric()->required(),
            ])->reorderable(false),
        ])];
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/../Fixtures/gasto-balance-form.blade.php');
    }
}
