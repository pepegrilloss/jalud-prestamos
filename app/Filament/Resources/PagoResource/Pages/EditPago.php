<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\Cuota;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPago extends EditRecord
{
    protected static string $resource = PagoResource::class;

    public function mount(int|string $record): void
    {
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            abort(403);
        }

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        $pago = $this->record;
        
        if (!$pago || !$pago->CuotaID) {
            return;
        }
        
        $cuota = \App\Models\Cuota::find($pago->CuotaID);
        $credito = $pago->credito;

        if (!$cuota || !$credito) {
            return;
        }

        // Recalcular estado de la cuota basado en MontoPagado
        $saldoPendiente = $cuota->MontoCuota - $cuota->MontoPagado;
        
        if ($cuota->MontoPagado >= $cuota->MontoCuota) {
            $cuota->update([
                'Estado' => Cuota::ESTADO_PAGADA,
                'FechaPago' => $pago->FechaPago,
                'SaldoPendiente' => 0.00,
            ]);
        } else {
            $estado = Cuota::ESTADO_PENDIENTE;
            $diasAtraso = 0;
            
            if (now()->isAfter($cuota->FechaVencimiento)) {
                $estado = Cuota::ESTADO_MORA;
                $diasAtraso = now()->diffInDays($cuota->FechaVencimiento);
            }
            
            $cuota->update([
                'SaldoPendiente' => $saldoPendiente,
                'Estado' => $estado,
                'DiasAtraso' => $diasAtraso,
            ]);
        }

        // Actualizar ProposicionCredito
        $proposicion = $credito->proposicion;
        
        if ($proposicion) {
            $montoCuotasTotal = $credito->cuotas()->sum('MontoCuota');
            $totalPagado = $credito->cuotas()->sum('MontoPagado');
            $proposicion->update([
                'SaldoPendiente' => $montoCuotasTotal - $totalPagado,
            ]);
        }

        Notification::make()
            ->success()
            ->title('Pago Actualizado')
            ->body('El pago ha sido actualizado correctamente')
            ->send();
    }
}
