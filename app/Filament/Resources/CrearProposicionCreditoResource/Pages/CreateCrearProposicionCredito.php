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
            if (!$esRefinanciamiento && !$this->clienteEstaAlDiaEnSusCuotas($clienteID)) {
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
            
            // Solo establecer MontoTotal si el usuario no lo modificó (si está vacío o igual al saldo pendiente)
            if (empty($data['MontoTotal']) || (float)$data['MontoTotal'] == (float)$infoRefinanciamiento['SaldoPendiente']) {
                $data['MontoTotal'] = $infoRefinanciamiento['SaldoPendiente'];
                $data['MontoTotalPagar'] = $infoRefinanciamiento['SaldoPendiente'];
            }
            
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

        // Asegurar que MontoTotalPagar esté calculado (Monto + Interés)
        if (empty($data['MontoTotalPagar']) && !empty($data['MontoTotal']) && !empty($data['MontoInteres'])) {
            $data['MontoTotalPagar'] = (float)$data['MontoTotal'] + (float)$data['MontoInteres'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Obtener los datos del formulario para verificar si es refinanciamiento
        $record = $this->record;
        
        if ($record->EsRefinanciamiento && $record->ProposicionCreditoAnteriorID) {
            $this->crearPagoAutomaticoRefinanciamiento($record);
        }
    }

    protected function crearPagoAutomaticoRefinanciamiento($proposicionRefinanciamiento): void
    {
        try {
            $proposicionAnterior = ProposicionCredito::find($proposicionRefinanciamiento->ProposicionCreditoAnteriorID);
            
            if (!$proposicionAnterior) {
                return;
            }

            // Obtener el crédito de la proposición anterior
            $creditoAnterior = Credito::where('ProposicionCreditoID', $proposicionAnterior->ProposicionCreditoID)
                ->where('Activo', true)
                ->first();

            if (!$creditoAnterior) {
                return;
            }

            // Obtener todas las cuotas pendientes del crédito anterior
            $cuotasPendientes = $creditoAnterior->cuotas()
                ->where('Activo', true)
                ->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA'])
                ->get();

            if ($cuotasPendientes->isEmpty()) {
                return;
            }

            // Calcular el saldo total pendiente
            $saldoTotalPendiente = (float) $cuotasPendientes->sum('SaldoPendiente');
            $montoRefinanciamiento = (float) $proposicionRefinanciamiento->MontoTotal;

            // Obtener el promotor cobrador del cliente
            $cliente = Cliente::find($proposicionRefinanciamiento->ClienteID);
            $promotorCobradorID = $cliente?->PromotorCobradorID;

            // PAGO 1: Crear pago por el saldo pendiente
            Pago::create([
                'CreditoID' => $creditoAnterior->CreditoID,
                'CuotaID' => $cuotasPendientes->first()->CuotaID,
                'PromotorCobradorID' => $promotorCobradorID,
                'MontoPagado' => $saldoTotalPendiente,
                'FechaPago' => now(),
                'EsMora' => false,
                'EsPagoAMayor' => false,
                'EsPagoForzado' => false,
                'Comentario' => "Pago automático por refinanciamiento. Proposición #{$proposicionRefinanciamiento->ProposicionCreditoID}. Saldo total: S/ " . number_format($saldoTotalPendiente, 2),
                'UsuarioRegistro' => Auth::user()?->name ?? 'Sistema',
                'Activo' => true,
            ]);

            // Si el monto refinanciado es mayor al saldo pendiente, crear PAGO 2 como pago a mayor
            if ($montoRefinanciamiento > $saldoTotalPendiente) {
                $montoAMayor = $montoRefinanciamiento - $saldoTotalPendiente;

                Pago::create([
                    'CreditoID' => $creditoAnterior->CreditoID,
                    'CuotaID' => $cuotasPendientes->first()->CuotaID,
                    'PromotorCobradorID' => $promotorCobradorID,
                    'MontoPagado' => $montoAMayor,
                    'FechaPago' => now(),
                    'EsMora' => false,
                    'EsPagoAMayor' => true, // Marcar como pago a mayor
                    'EsPagoForzado' => false,
                    'Comentario' => "Pago a mayor automático por refinanciamiento. Proposición #{$proposicionRefinanciamiento->ProposicionCreditoID}. Monto adicional: S/ " . number_format($montoAMayor, 2),
                    'UsuarioRegistro' => Auth::user()?->name ?? 'Sistema',
                    'Activo' => true,
                ]);
            }

            // Actualizar todas las cuotas pendientes como pagadas
            foreach ($cuotasPendientes as $cuota) {
                $cuota->update([
                    'SaldoPendiente' => 0,
                    'MontoPagado' => $cuota->MontoCuota,
                    'Estado' => 'PAGADO',
                    'FechaPago' => now(),
                ]);
            }

            $mensaje = "Se creó automáticamente un pago de S/ " . number_format($saldoTotalPendiente, 2) . " para cerrar la cuenta anterior.";
            if ($montoRefinanciamiento > $saldoTotalPendiente) {
                $montoAMayor = $montoRefinanciamiento - $saldoTotalPendiente;
                $mensaje .= " + Un pago a mayor de S/ " . number_format($montoAMayor, 2);
            }

            Notification::make()
                ->success()
                ->title('✓ Pago Automático')
                ->body($mensaje)
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->warning()
                ->title('⚠️ Aviso')
                ->body("La proposición se creó correctamente, pero hubo un error al crear el pago automático: {$e->getMessage()}")
                ->send();
        }
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

    /**
     * Verifica si un cliente está al día en el pago de sus cuotas
     * Calcula las cuotas que deberían estar pagadas hasta hoy y compara con lo pagado
     */
    protected function clienteEstaAlDiaEnSusCuotas($clienteID): bool
    {
        try {
            $hoy = \Carbon\Carbon::now();
            
            // Obtener todos los créditos activos del cliente
            $creditos = Credito::whereHas('proposicion', function ($query) use ($clienteID) {
                $query->where('ClienteID', $clienteID)->where('Activo', true);
            })->where('Activo', true)->get();
            
            foreach ($creditos as $credito) {
                // Obtener cuotas vencidas (FechaVencimiento <= hoy)
                $cuotasVencidas = $credito->cuotas()
                    ->where('FechaVencimiento', '<=', $hoy)
                    ->where('Estado', '!=', 'PAGADO')
                    ->get();
                
                if ($cuotasVencidas->isEmpty()) {
                    continue; // Sin cuotas vencidas, cliente está al día en este crédito
                }
                
                // Calcular el monto total de cuotas que deberían estar pagadas
                $montoCuotasEsperadas = $cuotasVencidas->sum('MontoCuota');
                
                // Calcular el total de pagos realizados en este crédito
                $totalPagos = $credito->pagos()
                    ->where('Activo', true)
                    ->where('FechaPago', '<=', $hoy)
                    ->sum('MontoPagado');
                
                // Si el total de pagos es menor a lo esperado, el cliente NO está al día
                if ($totalPagos < $montoCuotasEsperadas) {
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            // En caso de error, permitir (no bloquear)
            return true;
        }
    }
}
