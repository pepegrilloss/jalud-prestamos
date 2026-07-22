<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use App\Models\Cliente;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClienteProposicion extends EditRecord
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $clienteID = $this->record->ClienteID ?? null;
        $esRefinanciamiento = (bool) ($this->record->EsRefinanciamiento ?? false);

        if ($clienteID && !$esRefinanciamiento) {
            if (!\App\Services\ProposicionValidatorService::clienteEstaAlDiaEnSusCuotas((int) $clienteID)) {
                $cliente = Cliente::find($clienteID);
                Notification::make()
                    ->title('❌ Cliente no está al día')
                    ->body("El cliente '{$cliente->NombresApellidos}' no está al día en el pago de sus cuotas. No se puede guardar la edición hasta que regularice sus pagos.")
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        $valoresCredito = ClienteProposicionResource::calcularValoresCredito(
            $data['MontoTotal'] ?? $this->record->MontoTotal,
            $data['TasaInteres'] ?? $this->record->TasaInteres,
            $data['NumeroCuotas'] ?? $this->record->NumeroCuotas
        );

        $data = array_merge($data, $valoresCredito);

        if (!$this->record->credito) {
            $data['SaldoPendiente'] = $data['MontoTotalPagar'];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->credito) {
            \App\Services\SaldoPendienteService::recalcular($this->record->ProposicionCreditoID);
        }
    }
}
