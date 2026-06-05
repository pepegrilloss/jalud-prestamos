<?php

namespace Tests\Feature;

use App\Models\NivelAprobacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NivelAprobacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = \App\Models\Sede::firstOrCreate(
            ['Nombre' => 'Sede Test'],
            ['Codigo' => 'TEST', 'Activo' => true, 'FechaCreacion' => now()]
        );

        $niveles = [
            ['Nombre' => 'Gerencia',               'MontoMinimo' => 30000,    'MontoMaximo' => 99999999, 'Orden' => 1],
            ['Nombre' => 'Supervisor Operativo',   'MontoMinimo' => 0,        'MontoMaximo' => 30000,    'Orden' => 2],
            ['Nombre' => 'Jefe de Oficina Senior', 'MontoMinimo' => 0,        'MontoMaximo' => 5000,     'Orden' => 3],
            ['Nombre' => 'Jefe de Oficina Junior', 'MontoMinimo' => 0,        'MontoMaximo' => 2000,     'Orden' => 4],
        ];

        foreach ($niveles as $n) {
            NivelAprobacion::updateOrCreate(
                ['Nombre' => $n['Nombre'], 'SedeID' => $sede->SedeID],
                array_merge($n, ['SedeID' => $sede->SedeID, 'Activo' => true, 'FechaCreacion' => now()])
            );
        }
    }

    public function test_monto_1500_selecciona_jefe_junior(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(1500);
        $this->assertEquals('Jefe de Oficina Junior', $nivel->Nombre);
        $this->assertEquals(4, $nivel->Orden);
    }

    public function test_monto_2000_selecciona_jefe_junior(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(2000);
        $this->assertEquals('Jefe de Oficina Junior', $nivel->Nombre);
    }

    public function test_monto_2500_selecciona_jefe_senior(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(2500);
        $this->assertEquals('Jefe de Oficina Senior', $nivel->Nombre);
        $this->assertEquals(3, $nivel->Orden);
    }

    public function test_monto_4500_selecciona_jefe_senior(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(4500);
        $this->assertEquals('Jefe de Oficina Senior', $nivel->Nombre);
    }

    public function test_monto_5000_selecciona_jefe_senior(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(5000);
        $this->assertEquals('Jefe de Oficina Senior', $nivel->Nombre);
    }

    public function test_monto_10000_selecciona_supervisor(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(10000);
        $this->assertEquals('Supervisor Operativo', $nivel->Nombre);
        $this->assertEquals(2, $nivel->Orden);
    }

    public function test_monto_30000_selecciona_supervisor(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(30000);
        $this->assertEquals('Supervisor Operativo', $nivel->Nombre);
    }

    public function test_monto_35000_selecciona_gerencia(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(35000);
        $this->assertEquals('Gerencia', $nivel->Nombre);
        $this->assertEquals(1, $nivel->Orden);
    }

    public function test_monto_99999999_selecciona_gerencia(): void
    {
        $nivel = \App\Models\ProposicionCredito::obtenerNivelAprobacionRequerido(99999999);
        $this->assertEquals('Gerencia', $nivel->Nombre);
    }
}
