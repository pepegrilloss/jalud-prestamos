<?php

namespace Tests\Feature;

use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource;
use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\CiudadResource;
use App\Filament\Resources\GiroResource;
use App\Filament\Resources\NivelAprobacionResource;
use App\Filament\Resources\MotivoResource;
use App\Filament\Resources\PromotorCobradorResource;
use App\Filament\Resources\ProveedorResource;
use App\Filament\Resources\SedeResource;
use App\Filament\Resources\SubGiroResource;
use App\Filament\Resources\TasaMoraResource;
use App\Filament\Resources\TasaResource;
use App\Filament\Resources\TipoCreditoResource;
use App\Filament\Resources\TipoComprobanteResource;
use App\Filament\Resources\TipoExoneracionResource;
use App\Filament\Resources\TipoIgvResource;
use App\Filament\Resources\TipoPagoResource;
use App\Filament\Resources\ZonaResource;
use App\Models\TipoIgv;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

class GerenciaMaintenanceAccessTest extends TestCase
{
    public function test_gerencia_panel_registers_only_the_maintenance_resources(): void
    {
        $panel = Filament::getPanel('gerencia');
        $resources = $panel->getResources();

        $this->assertContains(Mantenimiento::class, $panel->getPages());

        foreach ([
            CiudadResource::class,
            GiroResource::class,
            NivelAprobacionResource::class,
            MotivoResource::class,
            PromotorCobradorResource::class,
            ProveedorResource::class,
            SedeResource::class,
            SubGiroResource::class,
            TasaMoraResource::class,
            TasaResource::class,
            TipoCreditoResource::class,
            TipoComprobanteResource::class,
            TipoComprobanteGastoResource::class,
            TipoExoneracionResource::class,
            TipoIgvResource::class,
            TipoPagoResource::class,
            ZonaResource::class,
        ] as $resource) {
            $this->assertContains($resource, $resources);
        }

        $this->assertNotContains(
            \App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource::class,
            $resources,
        );
    }

    public function test_users_with_gerencia_access_can_read_maintenance_records_but_not_edit_by_default(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('gerencia'));
        $this->assertSame('gerencia', Filament::getCurrentPanel()?->getId());

        $user = Mockery::mock(User::class);
        $user->shouldReceive('puedeAccederAGerencia')->andReturnTrue();
        $user->shouldReceive('can')->andReturnFalse();
        $user->shouldReceive('checkPermissionTo')->andReturnFalse();
        $user->shouldReceive('getAttribute')->with('PromotorCobradorID')->andReturn(123);
        auth()->setUser($user);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', TipoIgv::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', new TipoIgv()));
        $this->assertTrue(Mantenimiento::shouldRegisterNavigation());
        $this->assertTrue(SedeResource::canAccess());
        $this->assertTrue(TipoExoneracionResource::shouldRegisterNavigation());
        $this->assertTrue(TipoExoneracionResource::canViewAny());
    }

    public function test_gerencia_read_access_does_not_change_admin_panel_permissions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());

        $user = Mockery::mock(User::class);
        $user->shouldReceive('puedeAccederAGerencia')->andReturnTrue();
        $user->shouldReceive('can')->andReturnFalse();
        $user->shouldReceive('checkPermissionTo')->andReturnFalse();
        $user->shouldReceive('esAdmin')->andReturnFalse();
        auth()->setUser($user);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TipoIgv::class));
        $this->assertFalse(SedeResource::canAccess());
    }
}
