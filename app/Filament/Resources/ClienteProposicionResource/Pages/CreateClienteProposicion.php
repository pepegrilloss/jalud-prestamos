<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use App\Models\Cliente;
use App\Models\ProposicionCredito;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Crypt;

class CreateClienteProposicion extends CreateRecord
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function handleRecordCreation(array $data): ProposicionCredito
    {
        // Si viene el cliente encriptado desde la URL, desencriptarlo
        if ($encrypted = request()->query('cliente')) {
            try {
                $data['ClienteID'] = Crypt::decrypt($encrypted);
            } catch (\Exception $e) {
                // Cliente inválido o manipulado
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

        // Crear ProposicionCredito en lugar de Cliente
        return ProposicionCredito::create($data);
    }

    protected function getRedirectUrl(): string
    {
        // Redirigir de vuelta al índice del mismo resource
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Proposición de crédito creada exitosamente';
    }
}
