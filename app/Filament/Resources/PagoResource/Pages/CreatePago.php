<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\ProposicionCredito;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    // Guardar el valor de EsPagoInicial que el usuario seleccionó
    public ?bool $pagoInicialSeleccionado = null;

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
        $data = $this->form->getRawState();
        if (empty($data['TipoPago'])) {
            return [
                $this->getCancelFormAction(),
            ];
        }

        // Verificar si es pago inicial
        $esPagoInicial = false;
        try {
            $esPagoInicial = $this->esPagoInicial();
        } catch (\Exception $e) {
            $esPagoInicial = false;
        }

        if ($esPagoInicial) {
            // Acción para pago inicial: dos botones separados
            return [
                Actions\Action::make('registrar_como_inicial')
                    ->label('REGISTRA COMO INICIAL')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ Confirmar Registro de Pago')
                    ->modalDescription(function () {
                        try {
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
                    ->modalSubmitActionLabel('✓ Confirmar')
                    ->modalCancelActionLabel('✗ Cancelar')
                    ->action(function () {
                        $this->pagoInicialSeleccionado = true;
                        $this->create();
                    }),

                Actions\Action::make('registrar_normal')
                    ->label('REGISTRAR PAGO NORMAL')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ Confirmar Registro de Pago')
                    ->modalDescription(function () {
                        try {
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
                    ->modalSubmitActionLabel('✓ Confirmar')
                    ->modalCancelActionLabel('✗ Cancelar')
                    ->action(function () {
                        $this->pagoInicialSeleccionado = false;
                        $this->create();
                    }),

                $this->getCancelFormAction(),
            ];
        }

        // Acción normal: sin detección de pago inicial
        return [
            Actions\Action::make('confirmar_pago')
                ->label('Crear Pago')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('⚠️ Confirmar Registro de Pago')
                ->modalDescription(function () {
                    try {
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
                    $this->pagoInicialSeleccionado = false;
                    $this->create();
                }),

            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // SEGURIDAD: Rate limiting directo en Filament para creación de pagos
        // Máximo 10 pagos por usuario cada 60 minutos
        $userID = auth()->id();
        $key = "pago_creation_filament:{$userID}";
        
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $retryAfter = RateLimiter::availableIn($key);
            \Log::warning('SEGURIDAD - RATE LIMIT PAGO: Demasiados intentos', [
                'UserID' => $userID,
                'IP' => request()->ip(),
                'RetryAfter' => $retryAfter
            ]);
            throw new \Exception('Has intentado crear demasiados pagos. Intenta nuevamente en ' . ceil($retryAfter / 60) . ' minutos. Este intento ha sido registrado.');
        }
        
        RateLimiter::hit($key, 3600); // 1 hora
        
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
        
        $monto = (float) $data['MontoPagado'];
        
        // CRÍTICO: Validación exhaustiva de monto
        if ($monto <= 0) {
            throw new \Exception('El monto pagado debe ser mayor a S/ 0.00');
        }
        
        // Límite mínimo: S/ 0.01
        if ($monto < 0.01) {
            throw new \Exception('El monto mínimo a pagar es S/ 0.01');
        }
        
        // Límite máximo: S/ 1,000,000 (ajustar según políticas del negocio)
        $montoMaximo = 1000000;
        if ($monto > $montoMaximo) {
            throw new \Exception("El monto no puede exceder S/ {$montoMaximo}. Contacta a administración.");
        }

        // CRÍTICO: Validación de fechas permitidas
        $fechaPago = $data['FechaPago'] ?? now();
        
        // No puede ser en el futuro
        if (Carbon::parse($fechaPago)->gt(Carbon::now())) {
            throw new \Exception('La fecha del pago no puede ser en el futuro. Usa la fecha actual o anterior.');
        }
        
        // No puede ser más de 30 días en el pasado (ajustar según políticas)
        $fechaMinima = Carbon::now()->subDays(30);
        if (Carbon::parse($fechaPago)->lt($fechaMinima)) {
            throw new \Exception('La fecha del pago no puede ser más de 30 días anterior. Por favor contacta a administración para registros históricos.');
        }
        
        // Validar que la fecha esté dentro del período del crédito
        $creditoID = $data['CreditoID'] ?? null;
        if ($creditoID) {
            $credito = \App\Models\Credito::find($creditoID);
            if ($credito) {
                $fechaInicio = Carbon::parse($credito->FechaInicio);
                $fechaVencimiento = Carbon::parse($credito->FechaVencimiento);
                
                if (Carbon::parse($fechaPago)->lt($fechaInicio)) {
                    throw new \Exception('No se puede registrar un pago antes de la fecha de inicio del crédito.');
                }
                
                // Permitir pagos después del vencimiento (para mora), pero alertar
                if (Carbon::parse($fechaPago)->gt($fechaVencimiento->addDays(365))) {
                    throw new \Exception('Fecha de pago fuera del rango permitido del crédito. Contacta a administración.');
                }
            }
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
        
        // Validar y asignar el método de pago
        $metodoPagoValidos = ['EFECTIVO', 'YAPE_PLIN', 'TRANSFERENCIA_BANCARIA'];
        $tipoPago = $data['TipoPago'] ?? 'EFECTIVO';
        
        if (!in_array($tipoPago, $metodoPagoValidos)) {
            throw new \Exception('Método de pago inválido. Use: EFECTIVO, YAPE_PLIN o TRANSFERENCIA_BANCARIA');
        }
        
        $data['TipoPago'] = $tipoPago;
        
        // Mantener para compatibilidad con datos históricos (ya no se usan en el formulario)
        $data['EsMora'] = false;
        $data['EsPagoAMayor'] = false;
        
        // Usar el valor que el usuario seleccionó en los botones
        // Si no seleccionó nada explícitamente, hacer detección automática
        if ($this->pagoInicialSeleccionado !== null) {
            // El usuario seleccionó explícitamente REGISTRA COMO INICIAL o REGISTRAR PAGO NORMAL
            $data['EsPagoInicial'] = $this->pagoInicialSeleccionado;
        } else {
            // Para pagos normales (sin detección), usar false
            $data['EsPagoInicial'] = false;
        }
        
        // Inyectar fecha abierta en AMBOS campos de fecha (con hora actual)
        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaAAsignar = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
        
        if (!isset($data['FechaCreacion'])) {
            $data['FechaCreacion'] = $fechaAAsignar;
        }
        
        if (!isset($data['FechaPago'])) {
            $data['FechaPago'] = $fechaAAsignar;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // CRÍTICO: Envolver en transacción para evitar inconsistencias financieras
        try {
            DB::transaction(function () {
                $pagoOriginal = $this->record;

                if (!$pagoOriginal || !$pagoOriginal->CreditoID || !$pagoOriginal->CuotaID) {
                    \Log::warning('SEGURIDAD - CreatePago::afterCreate - No pago, CreditoID or CuotaID', ['pago' => $pagoOriginal]);
                    return;
                }

                // Asegurar que FechaPago tenga un valor
                $fechaPago = $pagoOriginal->FechaPago ?? now();

                \Log::info('SEGURIDAD - CreatePago::afterCreate - Iniciando procesamiento', [
                    'PagoID' => $pagoOriginal->PagoID,
                    'CreditoID' => $pagoOriginal->CreditoID,
                    'CuotaID' => $pagoOriginal->CuotaID,
                    'MontoPagado' => $pagoOriginal->MontoPagado,
                    'UsuarioID' => auth()->id(),
                    'IP' => request()->ip(),
                    'FechaPago' => $fechaPago
                ]);

                $credito = \App\Models\Credito::with('proposicion.cliente')->lockForUpdate()->find($pagoOriginal->CreditoID);

                if (!$credito) {
                    throw new \Exception('Crédito no encontrado: ' . $pagoOriginal->CreditoID);
                }

                // Obtener solo la cuota seleccionada con lock
                $cuota = \App\Models\Cuota::lockForUpdate()->find($pagoOriginal->CuotaID);

                if (!$cuota) {
                    throw new \Exception('Cuota no encontrada: ' . $pagoOriginal->CuotaID);
                }

                \Log::info('SEGURIDAD - CreatePago::afterCreate - Procesando cuota', [
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

                \Log::info('SEGURIDAD - CreatePago::afterCreate - Cuota actualizada', [
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
                    $nuevoSaldoPendiente = $montoCuotasTotal - $totalPagado;
                    
                    $proposicion->update([
                        'SaldoPendiente' => $nuevoSaldoPendiente,
                    ]);
                    \Log::info('SEGURIDAD - CreatePago::afterCreate - Proposición actualizada', [
                        'ProposicionID' => $proposicion->ProposicionCreditoID,
                        'ClienteID' => $credito->proposicion->ClienteID,
                        'TotalPagado' => $totalPagado,
                        'SaldoPendiente' => $nuevoSaldoPendiente
                    ]);

                    // Si el saldo llegó a 0, actualizar el estatus del crédito a SALDADO
                    if ($nuevoSaldoPendiente <= 0) {
                        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                        $fechaSaldamiento = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
                        
                        $credito->update([
                            'EstatusCreditoFinal' => 'SALDADO',
                            'FechaSaldamiento' => $fechaSaldamiento,
                        ]);
                        \Log::info('SEGURIDAD - CreatePago::afterCreate - Crédito marcado como SALDADO', [
                            'CreditoID' => $credito->CreditoID,
                            'ClienteID' => $credito->proposicion->ClienteID,
                            'FechaSaldamiento' => $fechaSaldamiento,
                            'UsuarioID' => auth()->id()
                        ]);
                    }
                }
            }, 2); // Máximo 2 reintentos si hay conflicto de concurrencia
            
            // Mostrar notificación al completar transacción
            $cuota = $this->record->cuota;
            Notification::make()
                ->success()
                ->title('✅ Pago Registrado Exitosamente')
                ->body("Pago de S/ {$this->record->MontoPagado} registrado en la cuota #{$cuota->NumeroCuota} correctamente.")
                ->send();

        } catch (\Exception $e) {
            // CRÍTICO: Log sin información sensible pero con contexto
            \Log::error('SEGURIDAD - CreatePago::afterCreate - Error en transacción', [
                'error_message' => $e->getMessage(),
                'PagoID' => $this->record->PagoID ?? 'desconocido',
                'UsuarioID' => auth()->id(),
                'IP' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            Notification::make()
                ->danger()
                ->title('❌ Error al procesar pago')
                ->body('El pago no se pudo registrar correctamente. Por favor contacta a administración.')
                ->send();
        }
    }

    /**
     * Verificar si el pago se realiza el mismo día que se generó el crédito
     */
    private function esPagoInicial(): bool
    {
        try {
            $data = $this->form->getRawState();
            $creditoID = $data['CreditoID'] ?? null;
            $fechaPago = $data['FechaPago'] ?? now();

            if (!$creditoID) {
                return false;
            }

            $credito = \App\Models\Credito::find($creditoID);
            
            if (!$credito || !$credito->FechaGeneracion) {
                return false;
            }

            // Comparar solo las fechas (sin hora)
            $fechaPagoStr = Carbon::parse($fechaPago)->toDateString();
            $fechaGeneracionStr = Carbon::parse($credito->FechaGeneracion)->toDateString();

            return $fechaPagoStr === $fechaGeneracionStr;
        } catch (\Exception $e) {
            \Log::error('Error en esPagoInicial:', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}