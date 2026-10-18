<?php

namespace Tests\Feature;

use App\Filament\Resources\GastoResource;
use App\Filament\Resources\GastoResource\Pages\ListGastos;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Tests\TestCase;

class GastoCreateFlowTest extends TestCase
{
    public function test_gerencia_gasto_create_action_uses_the_full_create_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('gerencia'));

        $page = new class extends ListGastos
        {
            public function headerActionsForTest(): array
            {
                return $this->getHeaderActions();
            }
        };

        $createAction = collect($page->headerActionsForTest())
            ->first(fn (Action $action): bool => $action->getName() === 'crear_gasto');

        $this->assertNotNull($createAction);
        $this->assertSame(GastoResource::getUrl('create'), $createAction->getUrl());
    }

    public function test_other_panels_keep_the_existing_create_action(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = new class extends ListGastos
        {
            public function headerActionsForTest(): array
            {
                return $this->getHeaderActions();
            }
        };

        $this->assertInstanceOf(\Filament\Actions\CreateAction::class, $page->headerActionsForTest()[0]);
    }
}
