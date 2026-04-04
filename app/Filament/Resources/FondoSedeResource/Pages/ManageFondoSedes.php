<?php

namespace App\Filament\Resources\FondoSedeResource\Pages;

use App\Filament\Resources\FondoSedeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFondoSedes extends ManageRecords
{
    protected static string $resource = FondoSedeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => auth()->user()->SedeID && stripos(auth()->user()->sede->Nombre, 'Gerencia') !== false),
        ];
    }
}
