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

        // Cargar los datos del pago en el formulario
        $this->form->fill([
            'ClienteID' => $this->record->cuota?->credito?->proposicion?->ClienteID,
            'CreditoID' => $this->record->cuota?->credito?->CreditoID,
            'CuotaID' => $this->record->CuotaID,
            'MontoPagado' => $this->record->MontoPagado,
            'FechaPago' => $this->record->FechaPago,
            'TipoPago' => $this->record->TipoPago ?? 'EFECTIVO',
            'Comentario' => $this->record->Comentario,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Editar Pago</span>
            </div>
        ");
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

        // Calcular el total pagado para esta cuota sumando desde la tabla pago
        $totalPagadoEnCuota = \App\Models\Pago::where('CuotaID', $cuota->CuotaID)
            ->where('Activo', 1)
            ->sum('MontoPagado');

        // Determinar el nuevo estado basándose en si está completamente pagada
        $nuevoEstado = $cuota->Estado;
        if ($totalPagadoEnCuota >= $cuota->MontoCuota) {
            $nuevoEstado = Cuota::ESTADO_PAGADA;
        } elseif (now()->isAfter($cuota->FechaVencimiento) && $totalPagadoEnCuota < $cuota->MontoCuota) {
            $nuevoEstado = Cuota::ESTADO_MORA;
        }

        // Actualizar solo el estado de la cuota
        $cuota->update([
            'Estado' => $nuevoEstado,
        ]);

        // Actualizar ProposicionCredito con el saldo pendiente total (calculado desde pagos)
        $proposicion = $credito->proposicion;
        
        if ($proposicion) {
            $montoCuotasTotal = $credito->cuotas()->sum('MontoCuota');
            $totalPagado = \App\Models\Pago::whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
                ->where('Activo', 1)
                ->sum('MontoPagado');
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
