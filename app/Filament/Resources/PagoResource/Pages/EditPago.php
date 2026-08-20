<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditPago extends EditRecord
{
    protected static string $resource = PagoResource::class;

    private ?float $montoOriginal = null;

    public function mount(int|string $record): void
    {
        if (auth()->user()?->esPromotorCobrador()) {
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
        $this->montoOriginal = (float) $this->record->MontoPagado;

        $montoNuevo = (float) ($data['MontoPagado'] ?? 0);
        if ($montoNuevo > 0 && $this->record->CreditoID) {
            $credito = \App\Models\Credito::with('proposicion')->find($this->record->CreditoID);
            if ($credito && $credito->proposicion) {
                $saldoPendiente = (float) $credito->proposicion->SaldoPendiente;
                $saldoConPagoActual = $saldoPendiente + $this->montoOriginal;

                if ($montoNuevo > $saldoConPagoActual) {
                    $esPagoAMayor = $data['EsPagoAMayor'] ?? $this->record->EsPagoAMayor;
                    $esPagoAMayorPorMora = $data['EsPagoAMayorPorMora'] ?? $this->record->EsPagoAMayorPorMora;
                    $esMora = $data['EsMora'] ?? $this->record->EsMora;

                    if (!$esPagoAMayor && !$esPagoAMayorPorMora && !$esMora) {
                        throw new \Exception(
                            "El cliente solo debe S/ " . number_format($saldoConPagoActual, 2)
                            . ". No puede pagar más de lo que debe."
                        );
                    }
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        try {
            DB::transaction(function () {
                $pago = $this->record;

                if (!$pago || !$pago->CuotaID) {
                    return;
                }

                $pago = \App\Models\Pago::lockForUpdate()->find($pago->PagoID);
                $cuota = \App\Models\Cuota::lockForUpdate()->find($pago->CuotaID);

                if (!$cuota) {
                    return;
                }

                // Registrar trazabilidad de edición (columnas dedicadas, no comentarios)
                $pago->update([
                    'FechaModificacion' => now(),
                    'UserModificacionID' => auth()->id(),
                ]);

                // Ajustar FondoSede con el delta del monto (nuevo - viejo)
                $montoNuevo = (float) $pago->MontoPagado;
                $montoViejo = $this->montoOriginal ?? 0;
                $delta = $montoNuevo - $montoViejo;

                if ($delta != 0 && $pago->SedeID) {
                    $fondoService = app(\App\Services\FondoSedeService::class);
                    if ($delta > 0) {
                        $fondoService->registrarIngresoRecaudo($pago->SedeID, $delta, $pago->PagoID, auth()->id());
                    } else {
                        $fondoService->registrarReversionRecaudo($pago->SedeID, abs($delta), $pago->PagoID, auth()->id());
                    }
                }

                // El PagoObserver::updated() se encarga de recalcular SaldoPendiente

                Log::info('SEGURIDAD - EditPago::afterSave - Pago editado', [
                    'PagoID' => $pago->PagoID,
                    'CreditoID' => $pago->CreditoID,
                    'MontoAnterior' => $this->montoOriginal,
                    'MontoNuevo' => $pago->MontoPagado,
                    'UsuarioID' => auth()->id(),
                    'IP' => request()->ip(),
                ]);
            }, 2);

            Notification::make()
                ->success()
                ->title('Pago Actualizado')
                ->body('El pago ha sido actualizado correctamente')
                ->send();

        } catch (\Exception $e) {
            Log::error('SEGURIDAD - EditPago::afterSave - Error en transacción', [
                'error_message' => $e->getMessage(),
                'PagoID' => $this->record->PagoID ?? 'desconocido',
                'UsuarioID' => auth()->id(),
                'timestamp' => now()->toIso8601String()
            ]);

            Notification::make()
                ->danger()
                ->title('Error al actualizar pago')
                ->body('No se pudo guardar los cambios. Contacta a administración.')
                ->send();
        }
    }
}
