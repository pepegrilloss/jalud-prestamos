<?php

namespace Tests\Feature;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource;
use App\Filament\Resources\MotivoResource;
use App\Filament\Resources\ProveedorResource;
use App\Filament\Resources\SedeResource;
use App\Filament\Resources\TipoComprobanteResource;
use App\Filament\Resources\TipoIgvResource;
use App\Models\TipoIgv;
use App\Models\TipoExoneracion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

class GerenciaMaintenanceAccessTest extends TestCase
{
    public function test_gerencia_panel_registers_only_maintenance_resources_relevant_to_expenses_and_purchases(): void
    {
        $panel = Filament::getPanel('gerencia');
        $resources = $panel->getResources();

        $this->assertContains(Mantenimiento::class, $panel->getPages());

        foreach ([
            MotivoResource::class,
            ProveedorResource::class,
            TipoComprobanteResource::class,
            TipoComprobanteGastoResource::class,
            TipoIgvResource::class,
        ] as $resource) {
            $this->assertContains($resource, $resources);
        }

        foreach ([
            \App\Filament\Resources\CiudadResource::class,
            \App\Filament\Resources\GiroResource::class,
            \App\Filament\Resources\NivelAprobacionResource::class,
            \App\Filament\Resources\PromotorCobradorResource::class,
            SedeResource::class,
            \App\Filament\Resources\SubGiroResource::class,
            \App\Filament\Resources\TasaMoraResource::class,
            \App\Filament\Resources\TasaResource::class,
            \App\Filament\Resources\TipoCreditoResource::class,
            \App\Filament\Resources\TipoExoneracionResource::class,
            \App\Filament\Resources\TipoPagoResource::class,
            \App\Filament\Resources\ZonaResource::class,
        ] as $resource) {
            $this->assertNotContains($resource, $resources);
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
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TipoExoneracion::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', new TipoIgv()));
        $this->assertTrue(Mantenimiento::shouldRegisterNavigation());
        $this->assertTrue(SedeResource::canAccess());
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
