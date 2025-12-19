<?php

namespace App\Filament\Resources\CrearProposicionCreditoResource\Pages;

use App\Filament\Resources\CrearProposicionCreditoResource;
use App\Filament\Resources\ClienteProposicionResource;
use App\Models\Cliente;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Crypt;

class CreateCrearProposicionCredito extends CreateRecord
{
    protected static string $resource = CrearProposicionCreditoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si viene el cliente encriptado desde la URL, desencriptarlo
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
