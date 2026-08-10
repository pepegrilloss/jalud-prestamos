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

        // NOTA: Las restricciones de negocio (credito activo mismo tipo, mora,
        // cliente observado, al dia, MMR, proposiciones pendientes) se validan
        // en la APROBACION (ProposicionAprobacionValidatorService), no al proponer.
        // Se permite proponer y el aprobador decide (con advertencias/confirmacion).

        // Manejar Refinanciamiento
        if ($esRefinanciamiento) {
            $proposicionAnterior = ProposicionCredito::withoutGlobalScope('sede')->find($data['ProposicionCreditoAnteriorID']);

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

        // SIEMPRE recalcular montos financieros en servidor.
        $valoresCredito = CrearProposicionCreditoResource::calcularValoresCredito(
            $data['MontoTotal'] ?? 0,
            $data['TasaInteres'] ?? 0,
            $data['NumeroCuotas'] ?? 1
        );

        $data = array_merge($data, $valoresCredito);
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
