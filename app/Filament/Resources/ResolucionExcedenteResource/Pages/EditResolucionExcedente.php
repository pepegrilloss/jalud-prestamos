<?php

namespace App\Filament\Resources\ResolucionExcedenteResource\Pages;

use App\Filament\Resources\ResolucionExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResolucionExcedente extends EditRecord
{
    protected static string $resource = ResolucionExcedenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->Estado === 'PENDIENTE'),
        ];
    }
}