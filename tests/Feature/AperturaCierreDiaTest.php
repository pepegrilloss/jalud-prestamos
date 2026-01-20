<?php

namespace Tests\Feature;

use App\Models\AperturaCierreDia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AperturaCierreDiaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Puede crear un registro de apertura
     */
    public function test_puede_crear_apertura(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $this->assertDatabaseHas('apertura_cierre_dia', [
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
        ]);
    }

    /**
     * Test: Verifica que hoy está abierto
     */
    public function test_verifica_dia_abierto(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $this->assertTrue(AperturaCierreDia::estaAbierto());
    }

    /**
     * Test: Verifica que hoy está cerrado
     */
    public function test_verifica_dia_cerrado(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'CERRADO',
            'FechaApertura' => now()->subHours(8),
            'FechaCierre' => now(),
            'UsuarioAperturaID' => $user->id,
            'UsuarioCierreID' => $user->id,
        ]);

        $this->assertFalse(AperturaCierreDia::estaAbierto());
    }

    /**
     * Test: Obtiene estado actual del día
     */
    public function test_obtiene_estado_actual(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $estado = AperturaCierreDia::estadoDiaActual();
        $this->assertEquals('ABIERTO', $estado);
    }

    /**
     * Test: Obtiene registro de hoy
     */
    public function test_obtiene_registro_hoy(): void
    {
        $user = User::factory()->create();

        $creado = AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $obtenido = AperturaCierreDia::hoyOHoy();

        $this->assertEquals($creado->AperturaCierreDiaID, $obtenido->AperturaCierreDiaID);
    }

    /**
     * Test: Solo un registro por día (unique)
     */
    public function test_solo_un_registro_por_dia(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        // Intentar crear otro para el mismo día
        $this->expectException(\Exception::class);

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'CERRADO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);
    }

    /**
     * Test: Relación con usuario apertura
     */
    public function test_relacion_usuario_apertura(): void
    {
        $user = User::factory()->create(['name' => 'Admin Test']);

        $registro = AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $this->assertEquals('Admin Test', $registro->usuarioApertura->name);
    }

    /**
     * Test: Relación con usuario cierre
     */
    public function test_relacion_usuario_cierre(): void
    {
        $userApertura = User::factory()->create(['name' => 'Admin 1']);
        $userCierre = User::factory()->create(['name' => 'Admin 2']);

        $registro = AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'CERRADO',
            'FechaApertura' => now()->subHours(8),
            'FechaCierre' => now(),
            'UsuarioAperturaID' => $userApertura->id,
            'UsuarioCierreID' => $userCierre->id,
        ]);

        $this->assertEquals('Admin 1', $registro->usuarioApertura->name);
        $this->assertEquals('Admin 2', $registro->usuarioCierre->name);
    }

    /**
     * Test: Validación del Service
     */
    public function test_servicio_valida_dia_abierto(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        // Debe pasar sin excepción
        $resultado = \App\Services\ValidacionDiaService::validarParaOperacion(
            'App\Models\Pago',
            false
        );

        $this->assertTrue($resultado);
    }

    /**
     * Test: Servicio bloquea día cerrado
     */
    public function test_servicio_bloquea_dia_cerrado(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'CERRADO',
            'FechaApertura' => now()->subHours(8),
            'FechaCierre' => now(),
            'UsuarioAperturaID' => $user->id,
            'UsuarioCierreID' => $user->id,
        ]);

        // Debe lanzar excepción
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        \App\Services\ValidacionDiaService::validarParaOperacion(
            'App\Models\Pago',
            true
        );
    }

    /**
     * Test: Servicio permite usuarios siempre
     */
    public function test_servicio_permite_usuarios(): void
    {
        // Sin crear ningún registro (día cerrado)

        // Debe pasar sin excepción porque usuarios son excepción
        $resultado = \App\Services\ValidacionDiaService::validarParaOperacion(
            'App\Models\User',
            false
        );

        $this->assertTrue($resultado);
    }

    /**
     * Test: Obtener información del estado
     */
    public function test_obtiene_info_estado(): void
    {
        $user = User::factory()->create();

        AperturaCierreDia::create([
            'Fecha' => today(),
            'EstadoDia' => 'ABIERTO',
            'FechaApertura' => now(),
            'UsuarioAperturaID' => $user->id,
        ]);

        $estado = \App\Services\ValidacionDiaService::obtenerEstado();

        $this->assertTrue($estado['abierto']);
        $this->assertEquals('ABIERTO', $estado['estado']);
        $this->assertNotNull($estado['registro']);
        $this->assertStringContainsString('abierto', strtolower($estado['mensaje']));
    }
}
