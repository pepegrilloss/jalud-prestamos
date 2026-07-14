<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SedeAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Mockery;
use Tests\TestCase;

class SedeAccessServiceTest extends TestCase
{
    public function test_usuario_de_sede_no_puede_consultar_otra_sede(): void
    {
        $user = $this->user(false, false, 10);

        $this->expectException(AuthorizationException::class);

        app(SedeAccessService::class)->resolveReportSedeId($user, '20');
    }

    public function test_supervisor_operativo_no_puede_usar_todas_las_sedes(): void
    {
        $user = $this->user(false, false, 10);

        $this->assertSame(10, app(SedeAccessService::class)->resolveReportSedeId($user, 'todas'));
    }

    public function test_usuario_con_ver_todas_las_sedes_puede_consultar_todas_o_una_sede(): void
    {
        $user = $this->user(false, true, 10);
        $service = app(SedeAccessService::class);

        $this->assertNull($service->resolveReportSedeId($user, 'todas'));
        $this->assertSame(20, $service->resolveReportSedeId($user, '20'));
    }

    protected function user(bool $esAdmin, bool $puedeVerTodas, int $sedeActiva): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('esAdmin')->andReturn($esAdmin);
        $user->shouldReceive('puedeVerTodasLasSedes')->andReturn($puedeVerTodas);
        $user->shouldReceive('getEffectiveSedeId')->andReturn($sedeActiva);

        return $user;
    }
}
