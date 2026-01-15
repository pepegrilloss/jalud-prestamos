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

                        $credito = \App\Models\Credito::with(['proposicion.cliente', 'proposicion.tipoCredito'])->find($data['CreditoID']);

                        if (!$credito || !$credito->proposicion || !$credito->proposicion->cliente) {
                            return 'No se pudo cargar la información del crédito o cliente.';
                        }

                        $cliente = $credito->proposicion->cliente;
                        $tipoCredito = e($credito->proposicion->tipoCredito?->Descripcion ?? 'N/A');
                        $monto = number_format($data['MontoPagado'], 2);

                        $nombre = e($cliente->NombresApellidos ?? '');

                        return new \Illuminate\Support\HtmlString(
                            '<div style="text-align:center; padding: 10px 0;">' .
                            '<div style="font-size: 1.1rem; color: #666; margin-bottom: 4px;">👤 Cliente:</div>' .
                            '<div style="font-size: 2.25rem; line-height: 2.5rem; font-weight: 800; color: #111; margin-bottom: 20px; text-transform: uppercase; letter-spacing: -0.025em;">' . $nombre . '</div>' .
                            '<div style="display: flex; justify-content: center; gap: 40px; border-top: 1px solid #eee; pt-4; margin-top: 10px; padding-top: 20px;">' .
                            '<div>' .
                            '<div style="font-size: 0.875rem; color: #666;">📝 Tipo de Crédito</div>' .
                            '<div style="font-size: 1.25rem; font-weight: 600;">' . $tipoCredito . '</div>' .
                            '</div>' .
                            '<div>' .
                            '<div style="font-size: 0.875rem; color: #666;">💰 Monto a Pagar</div>' .
                            '<div style="font-size: 1.5rem; font-weight: 700; color: #059669;">S/ ' . $monto . '</div>' .
                            '</div>' .
                            '</div>' .
                            '</div>'
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
            $pagoOriginal = $this->record;

            if (!$pagoOriginal || !$pagoOriginal->CreditoID || !$pagoOriginal->CuotaID) {
                \Log::warning('CreatePago::afterCreate - No pago, CreditoID or CuotaID', ['pago' => $pagoOriginal]);
                return;
            }

            // Asegurar que FechaPago tenga un valor
            $fechaPago = $pagoOriginal->FechaPago ?? now();

            \Log::info('CreatePago::afterCreate - Starting', [
                'PagoID' => $pagoOriginal->PagoID,
                'CreditoID' => $pagoOriginal->CreditoID,
                'CuotaID' => $pagoOriginal->CuotaID,
                'MontoPagado' => $pagoOriginal->MontoPagado,
                'FechaPago' => $fechaPago
            ]);

            $credito = \App\Models\Credito::find($pagoOriginal->CreditoID);

            if (!$credito) {
                \Log::error('CreatePago::afterCreate - Credito not found', ['CreditoID' => $pagoOriginal->CreditoID]);
                return;
            }

            // Obtener solo la cuota seleccionada
            $cuota = Cuota::find($pagoOriginal->CuotaID);

            if (!$cuota) {
                \Log::error('CreatePago::afterCreate - Cuota not found', ['CuotaID' => $pagoOriginal->CuotaID]);
                return;
            }

            // Aplicar el pago SOLO a la cuota seleccionada, sin prorrateo
            $saldoActual = $cuota->SaldoPendiente ?? $cuota->MontoCuota;
            $montoPagadoEnCuota = $pagoOriginal->MontoPagado;

            \Log::info('CreatePago::afterCreate - Procesando cuota', [
                'CuotaID' => $cuota->CuotaID,
                'NumeroCuota' => $cuota->NumeroCuota,
                'SaldoActual' => $saldoActual,
                'MontoPagado' => $montoPagadoEnCuota
            ]);

            $nuevoSaldoPendiente = $saldoActual - $montoPagadoEnCuota;
            $nuevoMontoPagado = ($cuota->MontoPagado ?? 0) + $montoPagadoEnCuota;

            // Determinar el nuevo estado
            $nuevoEstado = $cuota->Estado;
            if ($nuevoSaldoPendiente <= 0) {
                $nuevoEstado = Cuota::ESTADO_PAGADA;
            } elseif (now()->isAfter($cuota->FechaVencimiento) && $nuevoEstado !== Cuota::ESTADO_PAGADA) {
                $nuevoEstado = Cuota::ESTADO_MORA;
            }

            // Actualizar cuota
            $cuota->update([
                'MontoPagado' => $nuevoMontoPagado,
                'SaldoPendiente' => max(0, $nuevoSaldoPendiente),
                'Estado' => $nuevoEstado,
                'FechaPago' => $fechaPago,
            ]);

            \Log::info('CreatePago::afterCreate - Cuota actualizada', [
                'CuotaID' => $cuota->CuotaID,
                'NuevoEstado' => $nuevoEstado,
                'NuevoSaldoPendiente' => max(0, $nuevoSaldoPendiente),
                'NuevoMontoPagado' => $nuevoMontoPagado
            ]);

            // Actualizar la proposición con el saldo pendiente total
            $proposicion = $credito->proposicion;
            if ($proposicion) {
                $totalPagado = $credito->cuotas()->sum('MontoPagado');
                $proposicion->update([
                    'SaldoPendiente' => $proposicion->MontoTotal - $totalPagado,
                ]);
                \Log::info('CreatePago::afterCreate - Proposicion updated', [
                    'ProposicionID' => $proposicion->ProposicionID,
                    'TotalPagado' => $totalPagado,
                    'SaldoPendiente' => $proposicion->SaldoPendiente
                ]);
            }

            // Mostrar notificación
            Notification::make()
                ->success()
                ->title('✅ Pago Registrado')
                ->body("Pago de S/ {$pagoOriginal->MontoPagado} registrado en la cuota #{$cuota->NumeroCuota} correctamente.")
                ->send();

        } catch (\Exception $e) {
            \Log::error('CreatePago::afterCreate - Exception: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->danger()
                ->title('Error al procesar pago')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}