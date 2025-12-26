<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\Cuota;
use App\Models\ProposicionCredito;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    // Ocultar las acciones del header
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Agregar el botón ABAJO del formulario
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('confirmar_pago')
                ->label('Crear Pago')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('⚠️ Confirmar Registro de Pago')
                ->modalDescription(function () {
                    try {
                        $data = $this->form->getState();
                        
                        if (!isset($data['CreditoID']) || !isset($data['MontoPagado'])) {
                            return 'Por favor complete todos los campos requeridos.';
                        }
                        
                        $credito = \App\Models\Credito::with('proposicion.cliente')->find($data['CreditoID']);
                        
                        if (!$credito || !$credito->proposicion || !$credito->proposicion->cliente) {
                            return 'No se pudo cargar la información del cliente.';
                        }
                        
                        $cliente = $credito->proposicion->cliente;
                        $monto = number_format($data['MontoPagado'], 2);

                        $nombre = e($cliente->NombresApellidos ?? '');
                        $dni = e($cliente->DNI ?? $cliente->NumeroDocumento ?? '');

                        return new \Illuminate\Support\HtmlString(
                            '<div><strong>¿ESTÁ SEGURO DE REGISTRAR ESTE PAGO?</strong></div>' .
                            '<div style="margin-top:8px">👤 Cliente: <strong>' . $nombre . '</strong></div>' .
                            '<div>🆔 DNI: <strong>' . $dni . '</strong></div>' .
                            '<div>💰 Monto: S/ ' . $monto . '</div>'
                        );
                               
                    } catch (\Exception $e) {
                        return 'Error al cargar los datos.';
                    }
                })
                ->modalSubmitActionLabel('✓ Sí, Registrar')
                ->modalCancelActionLabel('✗ Cancelar')
                ->action(function () {
                    // Crear el registro
                    $this->create();
                }),
            
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si CuotaID no está establecido, obtener la primera cuota pendiente (solo cuotas reales)
        if (!isset($data['CuotaID']) || empty($data['CuotaID'])) {
            $primeraCuota = \App\Models\Cuota::where('CreditoID', $data['CreditoID'])
                ->where('Activo', 1)
                ->where('NumeroCuota', '>', 0)
                ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                ->orderBy('NumeroCuota')
                ->first();
            
            if ($primeraCuota) {
                $data['CuotaID'] = $primeraCuota->CuotaID;
            }
        }

        // Obtener el PromotorCobradorID del cliente del crédito
        $credito = \App\Models\Credito::find($data['CreditoID']);
        if ($credito && $credito->proposicion) {
            $cliente = $credito->proposicion->cliente;
            if ($cliente && $cliente->PromotorCobradorID) {
                $data['PromotorCobradorID'] = $cliente->PromotorCobradorID;
            }
        }

        // Obtener el usuario actual
        $data['UsuarioRegistro'] = auth()->user()->name ?? auth()->id();
        $data['Activo'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $pago = $this->record;
            
            if (!$pago || !$pago->CuotaID) {
                \Log::warning('CreatePago::afterCreate - No pago or CuotaID', ['pago' => $pago]);
                return;
            }
            
            \Log::info('CreatePago::afterCreate - Starting', [
                'PagoID' => $pago->PagoID,
                'CuotaID' => $pago->CuotaID,
                'CreditoID' => $pago->CreditoID,
                'MontoPagado' => $pago->MontoPagado
            ]);
            
            $cuota = \App\Models\Cuota::where('CuotaID', $pago->CuotaID)->first();
            
            if (!$cuota) {
                \Log::error('CreatePago::afterCreate - Cuota not found', ['CuotaID' => $pago->CuotaID]);
                return;
            }
            
            $credito = \App\Models\Credito::where('CreditoID', $pago->CreditoID)->first();
            
            if (!$credito) {
                \Log::error('CreatePago::afterCreate - Credito not found', ['CreditoID' => $pago->CreditoID]);
                return;
            }

            $nuevoMontoPagado = ($cuota->MontoPagado ?? 0) + $pago->MontoPagado;
            
            \Log::info('CreatePago::afterCreate - Updating cuota', [
                'CuotaID' => $cuota->CuotaID,
                'MontoPagadoAnterior' => $cuota->MontoPagado,
                'MontoPagado' => $pago->MontoPagado,
                'NuevoMontoPagado' => $nuevoMontoPagado,
                'MontoCuota' => $cuota->MontoCuota
            ]);
            
            if ($nuevoMontoPagado >= $cuota->MontoCuota) {
                $cuota->update([
                    'MontoPagado' => $nuevoMontoPagado,
                    'Estado' => Cuota::ESTADO_PAGADA,
                    'FechaPago' => $pago->FechaPago,
                    'SaldoPendiente' => 0.00,
                ]);
                \Log::info('CreatePago::afterCreate - Cuota marked as PAGADA');
            } else {
                $saldoPendiente = $cuota->MontoCuota - $nuevoMontoPagado;
                $estado = Cuota::ESTADO_PENDIENTE;
                $diasAtraso = 0;
                
                if (now()->isAfter($cuota->FechaVencimiento)) {
                    $estado = Cuota::ESTADO_MORA;
                    $diasAtraso = now()->diffInDays($cuota->FechaVencimiento);
                }
                
                $cuota->update([
                    'MontoPagado' => $nuevoMontoPagado,
                    'SaldoPendiente' => $saldoPendiente,
                    'Estado' => $estado,
                    'DiasAtraso' => $diasAtraso,
                ]);
                
                \Log::info('CreatePago::afterCreate - Cuota updated with estado: ' . $estado, [
                    'SaldoPendiente' => $saldoPendiente,
                    'DiasAtraso' => $diasAtraso
                ]);
            }

            $proposicion = $credito->proposicion;
            
            if ($proposicion) {
                $totalPagado = $credito->cuotas()->sum('MontoPagado');
                $proposicion->update([
                    'SaldoPendiente' => $proposicion->MontoTotal - $totalPagado,
                ]);
                \Log::info('CreatePago::afterCreate - Proposicion updated', [
                    'ProposicionID' => $proposicion->ProposicionID,
                    'SaldoPendiente' => $proposicion->SaldoPendiente
                ]);
            }

            Notification::make()
                ->success()
                ->title('✅ Pago Registrado')
                ->body("Pago de S/ {$pago->MontoPagado} registrado correctamente.")
                ->send();
                
        } catch (\Exception $e) {
            \Log::error('CreatePago::afterCreate - Exception: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->danger()
                ->title('Error al actualizar cuota')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}