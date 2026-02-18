<?php

namespace App\Filament\Resources\TasaMoraResource\Pages;

use App\Filament\Resources\TasaMoraResource;
use Filament\Resources\Pages\EditRecord;

class EditTasaMora extends EditRecord
{
    protected static string $resource = TasaMoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
