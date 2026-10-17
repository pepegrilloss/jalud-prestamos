<?php

namespace Tests\Feature;

use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource;
use App\Filament\Resources\MotivoResource;
use App\Filament\Resources\ProveedorResource;
use App\Filament\Resources\TipoComprobanteResource;
use App\Filament\Resources\TipoIgvResource;
use App\Models\AperturaCierreDia;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GerenciaMaintenanceDayGuardTest extends TestCase
{
    private array $resources = [
        MotivoResource::class,
        ProveedorResource::class,
        TipoComprobanteResource::class,
        TipoComprobanteGastoResource::class,
        TipoIgvResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('apertura_cierre_dia', function (Blueprint $table): void {
            $table->increments('AperturaCierreDiaID');
            $table->date('Fecha');
            $table->string('EstadoDia');
            $table->unsignedInteger('SedeID');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->resources as $resource) {
            $resource::skipAuthorization(false);
        }

        AperturaCierreDia::resetSedeFilterCache();
        Schema::dropIfExists('apertura_cierre_dia');

        parent::tearDown();
    }

    public function test_gerencia_maintenance_resources_block_mutations_when_day_is_closed(): void
    {
        $this->setGerenciaContext();

        foreach ($this->resources as $resource) {
            $resource::skipAuthorization();

            $this->assertFalse($resource::canCreate(), $resource);
            $this->assertFalse($resource::canEdit(new ($resource::getModel())), $resource);
            $this->assertFalse($resource::canDelete(new ($resource::getModel())), $resource);
        }

        $this->assertFalse(MotivoResource::canDeleteAny());
        $this->assertFalse(TipoComprobanteResource::canDeleteAny());
        $this->assertFalse(TipoIgvResource::canDeleteAny());
    }

    public function test_gerencia_maintenance_resources_allow_mutations_when_day_is_open(): void
    {
        $this->setGerenciaContext(dayOpen: true);

        foreach ($this->resources as $resource) {
            $resource::skipAuthorization();

            $this->assertTrue($resource::canCreate(), $resource);
            $this->assertTrue($resource::canEdit(new ($resource::getModel())), $resource);
            $this->assertTrue($resource::canDelete(new ($resource::getModel())), $resource);
        }

        $this->assertTrue(MotivoResource::canDeleteAny());
        $this->assertTrue(TipoComprobanteResource::canDeleteAny());
        $this->assertTrue(TipoIgvResource::canDeleteAny());
    }

    public function test_tipo_igv_day_guard_does_not_change_admin_panel_behavior(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(TipoIgvResource::dayAllowsGerenciaMutation());
    }

    private function setGerenciaContext(bool $dayOpen = false): void
    {
        Filament::setCurrentPanel(Filament::getPanel('gerencia'));

        $user = Mockery::mock(User::class);
        $user->shouldReceive('isPrivileged')->andReturnFalse();
        $user->shouldReceive('getAttribute')->with('SedeID')->andReturn(73);
        $user->shouldReceive('getEffectiveSedeId')->andReturn(73);
        $user->shouldReceive('puedeAccederAGerencia')->andReturnTrue();
        auth()->setUser($user);

        if ($dayOpen) {
            DB::table('apertura_cierre_dia')->insert([
                'Fecha' => today()->toDateString(),
                'EstadoDia' => 'ABIERTO',
                'SedeID' => 73,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        AperturaCierreDia::resetSedeFilterCache();
    }
}
