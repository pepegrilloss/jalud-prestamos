<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\ProposicionCredito;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    // Deshabilitar la notificación por defecto de Filament
    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }

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
                        // Usamos getRawFormData() o accedemos al estado de los componentes directamente
                        // para asegurar que el modal tenga los datos antes de la validación final
                        $data = $this->form->getRawState();

                        $creditoID = $data['CreditoID'] ?? null;
                        $montoPagado = $data['MontoPagado'] ?? null;

                        if (!$creditoID || !$montoPagado) {
                            return 'Por favor complete todos los campos requeridos (Cliente y Monto).';
                        }

                        $credito = \App\Models\Credito::with(['proposicion.cliente', 'proposicion.tipoCredito'])->find($creditoID);

                        if (!$credito || !$credito->proposicion || !$credito->proposicion->cliente) {
                            return 'No se pudo cargar la información del crédito o cliente.';
                        }

                        $cliente = $credito->proposicion->cliente;
                        $tipoCredito = e($credito->proposicion->tipoCredito?->Descripcion ?? 'N/A');
                        $monto = number_format($montoPagado, 2);

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
        // Obtener la zona del promotor actual
        $promotorCobrador = auth()->user()?->promotorCobrador;
        $zonaID = $promotorCobrador?->ZonaID;

        // Validar que el cliente esté presente
        if (empty($data['ClienteID'])) {
            throw new \Exception('El cliente es obligatorio.');
        }

        // 1. Asegurar primero el CreditoID (por si Filament lo quitó al estar oculto o no fue seteado)
        if (!isset($data['CreditoID']) || empty($data['CreditoID'])) {
            $clienteID = $data['ClienteID'] ?? null;
            if ($clienteID) {
                $creditoQuery = \App\Models\Credito::whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                    $q->where('ClienteID', $clienteID)
                        ->where('FueRefinanciada', 0);
                    if ($zonaID) {
                        $q->where('ZonaID', $zonaID);
                    }
                })->where('Activo', 1);

                $creditoUnico = $creditoQuery->first();

                if ($creditoUnico) {
                    $data['CreditoID'] = $creditoUnico->CreditoID;
                } else {
                    throw new \Exception('No se encontró un crédito activo para este cliente en su zona.');
                }
            } else {
                throw new \Exception('El cliente es obligatorio.');
            }
        }

        // Validar que el monto sea obligatorio y positivo
        if (empty($data['MontoPagado'])) {
            throw new \Exception('El monto pagado es obligatorio.');
        }
        if ($data['MontoPagado'] <= 0) {
            throw new \Exception('El monto pagado debe ser mayor a 0.');
        }

        // 2. Ahora que tenemos seguro el CreditoID, asegurar la CuotaID con la siguiente en secuencia
        if (!isset($data['CuotaID']) || empty($data['CuotaID'])) {
            $creditoID = $data['CreditoID'] ?? null;
            if ($creditoID) {
                // Obtener el máximo NumeroCuota que ya tiene pagos
                $ultimoCuotaConPago = \App\Models\Pago::where('pago.CreditoID', $creditoID)
                    ->where('pago.Activo', 1)
                    ->join('cuota', 'pago.CuotaID', '=', 'cuota.CuotaID')
                    ->max('cuota.NumeroCuota');

                // La siguiente cuota es la que viene después
                $siguienteCuotaNumber = ($ultimoCuotaConPago ?? 0) + 1;

                $siguienteCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                    ->where('NumeroCuota', $siguienteCuotaNumber)
                    ->where('Activo', 1)
                    ->first();

                if ($siguienteCuota) {
                    $data['CuotaID'] = $siguienteCuota->CuotaID;
                }
            }
        }

        // 3. Asignar PromotorCobradorID basado en la zona
        // El promotor cobrador debe ser del mismo usuario autenticado si su zona coincide con la de la proposición
        $promotorCobradorDelUsuario = auth()->user()?->promotorCobrador;
        
        if ($promotorCobradorDelUsuario) {
            // Obtener la zona del promotor cobrador del usuario
            $zonaDelPromotorCobradorDelUsuario = $promotorCobradorDelUsuario->ZonaID;
            
            // Obtener la proposición del crédito
            $creditoID = $data['CreditoID'] ?? null;
            if ($creditoID) {
                $credito = \App\Models\Credito::with('proposicion')->find($creditoID);
                if ($credito && $credito->proposicion) {
                    $zonaDelCredito = $credito->proposicion->ZonaID;
                    
                    // Si las zonas coinciden, asignar el PromotorCobradorID del usuario
                    if ($zonaDelPromotorCobradorDelUsuario === $zonaDelCredito) {
                        $data['PromotorCobradorID'] = $promotorCobradorDelUsuario->PromotorCobradorID;
                    } else {
                        // Si no coinciden, dejar como NULL
                        $data['PromotorCobradorID'] = null;
                    }
                }
            }
        } else {
            // Si el usuario no es promotor cobrador, dejar como NULL
            $data['PromotorCobradorID'] = null;
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
            $cuota = \App\Models\Cuota::find($pagoOriginal->CuotaID);

            if (!$cuota) {
                \Log::error('CreatePago::afterCreate - Cuota not found', ['CuotaID' => $pagoOriginal->CuotaID]);
                return;
            }

            \Log::info('CreatePago::afterCreate - Procesando cuota', [
                'CuotaID' => $cuota->CuotaID,
                'NumeroCuota' => $cuota->NumeroCuota,
                'MontoCuota' => $cuota->MontoCuota,
                'MontoPagado' => $pagoOriginal->MontoPagado
            ]);

            // Calcular el total pagado para esta cuota sumando desde la tabla pago
            $totalPagadoEnCuota = \App\Models\Pago::where('CuotaID', $cuota->CuotaID)
                ->where('Activo', 1)
                ->sum('MontoPagado');

            // Determinar el nuevo estado basándose en si está completamente pagada
            $nuevoEstado = $cuota->Estado;
            if ($totalPagadoEnCuota >= $cuota->MontoCuota) {
                // Cuota completamente pagada
                $nuevoEstado = \App\Models\Cuota::ESTADO_PAGADA;
            } elseif (now()->isAfter($cuota->FechaVencimiento) && $totalPagadoEnCuota < $cuota->MontoCuota) {
                // Cuota vencida y con saldo pendiente
                $nuevoEstado = \App\Models\Cuota::ESTADO_MORA;
            }

            // Actualizar solo el estado de la cuota
            $cuota->update([
                'Estado' => $nuevoEstado,
            ]);

            \Log::info('CreatePago::afterCreate - Cuota actualizada', [
                'CuotaID' => $cuota->CuotaID,
                'NuevoEstado' => $nuevoEstado,
                'TotalPagado' => $totalPagadoEnCuota
            ]);

            // Actualizar la proposición con el saldo pendiente total (calculado desde pagos)
            $proposicion = $credito->proposicion;
            if ($proposicion) {
                $montoCuotasTotal = $credito->cuotas()->sum('MontoCuota');
                $totalPagado = \App\Models\Pago::whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
                    ->where('Activo', 1)
                    ->sum('MontoPagado');
                $proposicion->update([
                    'SaldoPendiente' => $montoCuotasTotal - $totalPagado,
                ]);
                \Log::info('CreatePago::afterCreate - Proposicion updated', [
                    'ProposicionID' => $proposicion->ProposicionCreditoID,
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