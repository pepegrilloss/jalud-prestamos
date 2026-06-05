<?php

namespace App\Filament\Resources\CrearProposicionCreditoResource\Pages;

use App\Filament\Resources\CrearProposicionCreditoResource;
use App\Filament\Resources\ClienteProposicionResource;
use App\Models\Cliente;
use App\Models\ProposicionCredito;
use App\Models\Credito;
use App\Models\Pago;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class CreateCrearProposicionCredito extends CreateRecord
{
    protected static string $resource = CrearProposicionCreditoResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='" . static::getResource()::getUrl('index') . "' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Generar Proposición</span>
            </div>
        ");
    }

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

            // NUEVA VALIDACIÓN: Verificar si el cliente está al día en sus cuotas
            if (!$esRefinanciamiento && !\App\Services\ProposicionValidatorService::clienteEstaAlDiaEnSusCuotas($clienteID)) {
                $cliente = Cliente::find($clienteID);
                Notification::make()
                    ->title('❌ Cliente no está al día')
                    ->body("El cliente '{$cliente->NombresApellidos}' no está al día en el pago de sus cuotas. No se puede crear una nueva proposición hasta que regularice sus pagos.")
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

            // Si por alguna razón viene vacío, usamos el saldo pendiente
            if (empty($data['MontoTotal'])) {
                $data['MontoTotal'] = $infoRefinanciamiento['SaldoPendiente'];
            }

            $data['EsRefinanciamiento'] = true;

            // Ya vienen cargados: TasaID, TasaInteres, Plazo, NumeroCuotas, TasaMora
        } else {
            // Si NO es refinanciamiento, asegurarse de que se setee a false
            $data['EsRefinanciamiento'] = false;
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

        // Inyectar fecha abierta si no está seteada (con hora actual)
        if (!isset($data['FechaPropuesta'])) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $data['FechaPropuesta'] = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
        }

        $data['Estado'] = 'PENDIENTE';
        $data['Activo'] = true;

        // SIEMPRE recalcular MontoTotalPagar y SaldoPendiente en servidor
        $montoTotal = (float) ($data['MontoTotal'] ?? 0);
        $montoInteres = (float) ($data['MontoInteres'] ?? 0);
        $data['MontoTotalPagar'] = $montoTotal + $montoInteres;
        $data['SaldoPendiente'] = $data['MontoTotalPagar'];

        return $data;
    }

    protected function afterCreate(): void
    {
        // El pago automático se hace ahora en GenerarCreditoResource al generar el crédito
    }

    protected function crearPagoAutomaticoRefinanciamiento($proposicionRefinanciamiento): void
    {
        // MÉTODO REMOVIDO: El pago automático se realiza en GenerarCreditoResource
        // cuando se genera el crédito, no cuando se crea la proposición
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
