<?php

namespace Tests\Feature;

use App\Models\AprobacionProposicion;
use App\Models\NivelAprobacion;
use App\Models\ProposicionCredito;
use App\Models\Sede;
use App\Models\User;
use App\Models\UserNivelAprobacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NivelAprobacionTest extends TestCase
{
    use RefreshDatabase;

    protected Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::firstOrCreate(
            ['Nombre' => 'Sede Test'],
            ['Codigo' => 'TEST', 'Activo' => true, 'FechaCreacion' => now()]
        );

        $niveles = [
            ['Nombre' => 'Gerencia',               'MontoMinimo' => 0,    'MontoMaximo' => 99999999, 'Orden' => 1],
            ['Nombre' => 'Supervisor Operativo',   'MontoMinimo' => 0,    'MontoMaximo' => 30000,    'Orden' => 2],
            ['Nombre' => 'Jefe de Oficina Senior', 'MontoMinimo' => 0,    'MontoMaximo' => 5000,     'Orden' => 3],
            ['Nombre' => 'Jefe de Oficina Junior', 'MontoMinimo' => 0,    'MontoMaximo' => 2000,     'Orden' => 4],
        ];

        foreach ($niveles as $n) {
            NivelAprobacion::updateOrCreate(
                ['Nombre' => $n['Nombre'], 'SedeID' => $this->sede->SedeID],
                array_merge($n, ['SedeID' => $this->sede->SedeID, 'Activo' => true, 'FechaCreacion' => now()])
            );
        }
    }

    protected function nivel(string $nombre): NivelAprobacion
    {
        return NivelAprobacion::where('Nombre', $nombre)
            ->where('SedeID', $this->sede->SedeID)
            ->firstOrFail();
    }

    protected function crearUsuarioConNivel(string $nombreNivel): User
    {
        $user = User::factory()->create(['SedeID' => $this->sede->SedeID]);
        UserNivelAprobacion::create([
            'UserID'           => $user->id,
            'NivelAprobacionID' => $this->nivel($nombreNivel)->NivelAprobacionID,
            'Activo'           => true,
            'FechaAsignacion'  => now(),
            'SedeID'           => $this->sede->SedeID,
        ]);
        return $user;
    }

    public function test_monto_1500_asignado_a_jefe_junior(): void
    {
        $nivel = ProposicionCredito::obtenerNivelAprobacionRequerido(1500);
        $this->assertEquals('Jefe de Oficina Junior', $nivel->Nombre);
        $this->assertEquals(4, $nivel->Orden);
    }

    public function test_monto_4500_asignado_a_jefe_senior(): void
    {
        $nivel = ProposicionCredito::obtenerNivelAprobacionRequerido(4500);
        $this->assertEquals('Jefe de Oficina Senior', $nivel->Nombre);
    }

    public function test_monto_10000_asignado_a_supervisor(): void
    {
        $nivel = ProposicionCredito::obtenerNivelAprobacionRequerido(10000);
        $this->assertEquals('Supervisor Operativo', $nivel->Nombre);
    }

    public function test_monto_35000_asignado_a_gerencia(): void
    {
        $nivel = ProposicionCredito::obtenerNivelAprobacionRequerido(35000);
        $this->assertEquals('Gerencia', $nivel->Nombre);
    }

    public function test_supervisor_puede_aprobar_monto_de_4000_asignado_a_senior(): void
    {
        $supervisor = $this->crearUsuarioConNivel('Supervisor Operativo');
        $this->assertTrue($supervisor->puedeAprobarPorMonto(4000.0));
    }

    public function test_supervisor_puede_aprobar_monto_de_1000_asignado_a_junior(): void
    {
        $supervisor = $this->crearUsuarioConNivel('Supervisor Operativo');
        $this->assertTrue($supervisor->puedeAprobarPorMonto(1000.0));
    }

    public function test_senior_puede_aprobar_monto_de_1000(): void
    {
        $senior = $this->crearUsuarioConNivel('Jefe de Oficina Senior');
        $this->assertTrue($senior->puedeAprobarPorMonto(1000.0));
        $this->assertTrue($senior->puedeAprobarPorMonto(4500.0));
    }

    public function test_senior_NO_puede_aprobar_monto_de_10000(): void
    {
        $senior = $this->crearUsuarioConNivel('Jefe de Oficina Senior');
        $this->assertFalse($senior->puedeAprobarPorMonto(10000.0));
    }

    public function test_junior_NO_puede_aprobar_monto_de_3000(): void
    {
        $junior = $this->crearUsuarioConNivel('Jefe de Oficina Junior');
        $this->assertFalse($junior->puedeAprobarPorMonto(3000.0));
    }

    public function test_gerencia_es_super_aprobador_por_rango(): void
    {
        $gerencia = $this->crearUsuarioConNivel('Gerencia');
        $this->assertTrue($gerencia->puedeAprobarPorMonto(1000.0));
        $this->assertTrue($gerencia->puedeAprobarPorMonto(4500.0));
        $this->assertTrue($gerencia->puedeAprobarPorMonto(99999999.0));
    }

    public function test_editar_monto_reasigna_nivel_cuando_cambia_de_rango(): void
    {
        $proposicion = ProposicionCredito::create([
            'CodigoCredito' => 'C-TEST001',
            'MontoTotal' => 500,
            'Estado' => 'PENDIENTE',
            'SedeID' => $this->sede->SedeID,
        ]);

        $proposicion->crearAprobacionesRequeridas();

        $this->assertEquals(
            $this->nivel('Jefe de Oficina Junior')->NivelAprobacionID,
            $proposicion->fresh()->NivelAprobacionRequerido
        );

        $proposicion->MontoTotal = 4500;
        $proposicion->save();
        $proposicion->refresh();

        $this->assertEquals(
            $this->nivel('Jefe de Oficina Senior')->NivelAprobacionID,
            $proposicion->NivelAprobacionRequerido
        );

        $aprobaciones = $proposicion->aprobaciones()->get();
        $this->assertCount(1, $aprobaciones);
        $this->assertEquals('PENDIENTE', $aprobaciones->first()->Estado);
        $this->assertEquals(
            $this->nivel('Jefe de Oficina Senior')->NivelAprobacionID,
            $aprobaciones->first()->NivelAprobacionID
        );
    }
}
