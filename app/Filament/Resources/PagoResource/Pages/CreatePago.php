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
            $pagoOriginal = $this->record;
            
            if (!$pagoOriginal || !$pagoOriginal->CreditoID) {
                \Log::warning('CreatePago::afterCreate - No pago or CreditoID', ['pago' => $pagoOriginal]);
                return;
            }
            
            // Asegurar que FechaPago tenga un valor
            $fechaPago = $pagoOriginal->FechaPago ?? now();
            
            \Log::info('CreatePago::afterCreate - Starting', [
                'PagoID' => $pagoOriginal->PagoID,
                'CreditoID' => $pagoOriginal->CreditoID,
                'MontoPagado' => $pagoOriginal->MontoPagado,
                'FechaPago' => $fechaPago
            ]);
            
            $credito = \App\Models\Credito::find($pagoOriginal->CreditoID);
            
            if (!$credito) {
                \Log::error('CreatePago::afterCreate - Credito not found', ['CreditoID' => $pagoOriginal->CreditoID]);
                return;
            }

            // Obtener todas las cuotas pendientes (no pagadas)
            $cuotasPendientes = Cuota::where('CreditoID', $pagoOriginal->CreditoID)
                ->where('Activo', 1)
                ->where('NumeroCuota', '>', 0)
                ->where('Estado', '!=', Cuota::ESTADO_PAGADA)
                ->orderBy('NumeroCuota')
                ->get();

            $montoDisponible = $pagoOriginal->MontoPagado;
            $pagosCreados = [];

            // Distribuir el pago entre las cuotas pendientes
            foreach ($cuotasPendientes as $cuota) {
                if ($montoDisponible <= 0) {
                    break;
                }

                $saldoActual = $cuota->SaldoPendiente ?? $cuota->MontoCuota;
                $montoPagadoEnCuota = min($montoDisponible, $saldoActual);

                \Log::info('CreatePago::afterCreate - Procesando cuota', [
                    'CuotaID' => $cuota->CuotaID,
                    'NumeroCuota' => $cuota->NumeroCuota,
                    'SaldoActual' => $saldoActual,
                    'MontoPagadoEnCuota' => $montoPagadoEnCuota,
                    'MontoDisponible' => $montoDisponible
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

                // Si esta es la cuota original (la que estaba seleccionada), actualizar el pago original
                if ($cuota->CuotaID == $pagoOriginal->CuotaID) {
                    $pagoOriginal->update([
                        'MontoPagado' => $montoPagadoEnCuota,
                    ]);
                    $pagosCreados[] = $pagoOriginal->PagoID;
                } else {
                    // Crear un nuevo registro de pago para las cuotas adicionales
                    $pagoAdicional = \App\Models\Pago::create([
                        'CreditoID' => $pagoOriginal->CreditoID,
                        'CuotaID' => $cuota->CuotaID,
                        'PromotorCobradorID' => $pagoOriginal->PromotorCobradorID,
                        'MontoPagado' => $montoPagadoEnCuota,
                        'FechaPago' => $fechaPago,
                        'EsMora' => $pagoOriginal->EsMora ?? false,
                        'EsPagoAMayor' => false,
                        'EsPagoForzado' => $pagoOriginal->EsPagoForzado ?? false,
                        'Comentario' => $pagoOriginal->Comentario ? $pagoOriginal->Comentario . ' (Pago adelantado distribuido)' : 'Pago adelantado distribuido',
                        'UsuarioRegistro' => $pagoOriginal->UsuarioRegistro,
                        'Activo' => true,
                    ]);
                    $pagosCreados[] = $pagoAdicional->PagoID;

                    \Log::info('CreatePago::afterCreate - Pago adicional creado', [
                        'PagoID' => $pagoAdicional->PagoID,
                        'CuotaID' => $cuota->CuotaID,
                        'Monto' => $montoPagadoEnCuota,
                        'FechaPago' => $fechaPago
                    ]);
                }

                $montoDisponible -= $montoPagadoEnCuota;
            }

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

            // Mostrar notificación con resumen
            $cuotasCubiertas = count($pagosCreados);
            if ($cuotasCubiertas > 1) {
                Notification::make()
                    ->success()
                    ->title('✅ Pago Adelantado Registrado')
                    ->body("Pago de S/ {$pagoOriginal->MontoPagado} distribuido en {$cuotasCubiertas} cuota(s).")
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title('✅ Pago Registrado')
                    ->body("Pago de S/ {$pagoOriginal->MontoPagado} registrado correctamente.")
                    ->send();
            }
                
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