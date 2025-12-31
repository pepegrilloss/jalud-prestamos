<?php

namespace App\Filament\Resources\CrearProposicionCreditoResource\Pages;

use App\Filament\Resources\CrearProposicionCreditoResource;
use App\Filament\Resources\ClienteProposicionResource;
use App\Models\Cliente;
use App\Models\ProposicionCredito;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

class CreateCrearProposicionCredito extends CreateRecord
{
    protected static string $resource = CrearProposicionCreditoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validar que el cliente no tenga más de 2 proposiciones activas
        $clienteID = $data['ClienteID'] ?? null;
        
        if ($clienteID) {
            $proposicionesActivas = ProposicionCredito::contarProposicionesActivas($clienteID);
            
            if ($proposicionesActivas >= 2) {
                $cliente = Cliente::find($clienteID);
                Notification::make()
                    ->title('❌ No se puede crear proposición')
                    ->body("El cliente '{$cliente->NombresApellidos}' ya tiene 2 proposiciones activas. Se permite un máximo de 2 proposiciones simultáneas.")
                    ->danger()
                    ->send();
                
                $this->halt();
            }
        }

        if ($encrypted = request()->query('cliente')) {
            try {
                $data['ClienteID'] = Crypt::decrypt($encrypted);
            } catch (\Exception $e) {
                // Cliente inválido o manipulado, ignorar
            }
        }

        // Obtener el DNI del cliente para CodigoCliente
        if (!empty($data['ClienteID'])) {
            $cliente = Cliente::find($data['ClienteID']);
            if ($cliente) {
                $data['CodigoCliente'] = $cliente->DNI;
            }
        }

        $data['UserProponenteID'] = auth()->id();
        $data['FechaPropuesta'] = now();
        $data['Estado'] = 'PENDIENTE';
        $data['Activo'] = true;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirigir de vuelta al ClienteProposicionResource
        return ClienteProposicionResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Proposición de crédito creada exitosamente';
    }
}
