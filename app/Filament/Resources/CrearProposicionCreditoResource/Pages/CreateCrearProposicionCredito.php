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
        $clienteID = $data['ClienteID'] ?? null;
        $esRefinanciamiento = isset($data['ProposicionCreditoAnteriorID']) && $data['ProposicionCreditoAnteriorID'];
        
        // Validar que el cliente no tenga más de 2 proposiciones activas (EXCEPTO si es refinanciamiento)
        if ($clienteID) {
            $proposicionesActivas = ProposicionCredito::contarProposicionesActivas($clienteID);
            
            // Si NO es refinanciamiento, aplicar el límite de 2
            if (!$esRefinanciamiento && $proposicionesActivas >= 2) {
                $cliente = Cliente::find($clienteID);
                Notification::make()
                    ->title('❌ No se puede crear proposición')
                    ->body("El cliente '{$cliente->NombresApellidos}' ya tiene 2 proposiciones activas. Se permite un máximo de 2 proposiciones simultáneas.")
                    ->danger()
                    ->send();
                
                $this->halt();
            }
        }

        // Manejar Refinanciamiento
        if ($esRefinanciamiento) {
            $proposicionAnterior = ProposicionCredito::find($data['ProposicionCreditoAnteriorID']);
            
            if (!$proposicionAnterior) {
                Notification::make()
                    ->title('❌ Error')
                    ->body("No se encontró el crédito a refinanciar.")
                    ->danger()
                    ->send();
                $this->halt();
            }

            // Obtener información del crédito anterior
            $infoRefinanciamiento = $proposicionAnterior->obtenerInfoRefinanciamiento();
            
            // Establecer MontoTotal desde el saldo pendiente
            $data['MontoTotal'] = $infoRefinanciamiento['SaldoPendiente'];
            $data['MontoTotalPagar'] = $infoRefinanciamiento['SaldoPendiente'];
            $data['EsRefinanciamiento'] = true;
            
            // Ya vienen cargados: TasaID, TasaInteres, Plazo, NumeroCuotas, TasaMora
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
