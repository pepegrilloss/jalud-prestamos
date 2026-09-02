<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use App\Services\PrestamoBancarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePrestamoBancario extends CreateRecord
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected static string $view = 'filament.resources.prestamo-bancario.create-prestamo-bancario';

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PrestamoBancarioResource::normalizarDatosCronograma($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(PrestamoBancarioService::class)->crearPrestamo($data);
    }

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label('Guardar préstamo')
            ->submit('create');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Préstamo y cronograma guardados correctamente';
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        Notification::make()
            ->danger()
            ->title('No se guardó el préstamo')
            ->body('Revise los campos marcados del cronograma antes de guardar.')
            ->send();
    }
}
