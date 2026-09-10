<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPrestamoBancario extends EditRecord
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected static string $view = 'filament.resources.prestamo-bancario.edit-prestamo-bancario';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['PrestamistaTercero'] = $this->record->TipoPrestamista === PrestamoBancario::TIPO_TERCERO
            ? $this->record->Banco
            : null;
        $data['Cronograma'] = $this->record->cuotas()
            ->orderBy('Numero')
            ->get()
            ->map(fn ($cuota): array => [
                'Numero' => $cuota->Numero,
                'FechaVencimiento' => $cuota->FechaVencimiento?->toDateString(),
                'Capital' => $cuota->Capital,
                'Interes' => $cuota->Interes,
                'Comision' => $cuota->Comision,
                'Seguros' => $cuota->Seguros,
                'MontoCuota' => $cuota->MontoCuota,
                'SaldoDeuda' => $cuota->SaldoDeuda,
            ])->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PrestamoBancarioResource::normalizarDatosCronograma($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PrestamoBancarioService::class)->actualizarPrestamoSinPagos($record, $data);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')->label('Guardar cambios')->submit('save');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Préstamo y cronograma actualizados correctamente';
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        Notification::make()
            ->danger()
            ->title('No se guardaron los cambios')
            ->body('No es posible modificar préstamos con pagos registrados.')
            ->send();
    }
}
