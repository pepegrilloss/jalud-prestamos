<?php

namespace Tests\Unit;

use App\Models\User;
use Filament\Panel;
use Mockery;
use Tests\TestCase;

class UserPanelAccessTest extends TestCase
{
    public function test_oficial_cumplimiento_sbs_no_puede_acceder_a_paneles_de_jalud_prestamos(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('oficial_cumplimiento_sbs')
            ->andReturnTrue();

        foreach (['admin', 'gerencia', 'cumplimiento'] as $panelId) {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn($panelId);

            $this->assertFalse($user->canAccessPanel($panel));
        }
    }

    public function test_oficial_cumplimiento_sbs_cierra_sesion_antes_de_ir_al_sistema_independiente(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 999;
        $user->shouldReceive('hasRole')
            ->with('oficial_cumplimiento_sbs')
            ->andReturnTrue();

        $this->actingAs($user)
            ->post(route('cumplimiento.logout-redirect'))
            ->assertRedirect(route('cumplimiento.redirect'));

        $this->assertGuest();
    }
}
