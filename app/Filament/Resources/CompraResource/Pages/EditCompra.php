<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Models\FondoSede;
use App\Models\Sede;
use App\Services\FondoSedeService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    private ?float $totalAnterior = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->totalAnterior = (float) ($this->record->Total ?? 0);

        if (isset($data['detalles'])) {
            foreach ($data['detalles'] as &$detalle) {
                $cantidad = floatval($detalle['Cantidad'] ?? 0);
                $precio = floatval($detalle['PrecioUnitario'] ?? 0);
                $detalle['Subtotal'] = round($cantidad * $precio, 2);
            }
        }

        $subtotalBase = collect($data['detalles'] ?? [])->sum(fn($item) => floatval($item['Subtotal'] ?? 0));

        if (empty($data['SubtotalBase']) || floatval($data['SubtotalBase']) == 0) {
            $data['SubtotalBase'] = $subtotalBase;
        }

        $data['MontoIGV'] = floatval($data['MontoIGV'] ?? 0);
        // Si el usuario proporcionó un Total manualmente, respetarlo
        // Solo recalcular si viene vacío o es 0
        $totalManual = floatval($data['Total'] ?? 0);
        if ($totalManual > 0) {
            // El usuario ya definió el total manualmente, usarlo
        } else {
            $data['Total'] = $subtotalBase + $data['MontoIGV'];
        }

        // Si es CRÉDITO, poner como pendiente
        if (($data['TipoCompra'] ?? 'CONTADO') === 'CREDITO') {
            $data['EstadoPago'] = $data['EstadoPago'] ?? 'PENDIENTE';
        }

        // Validar saldo si es CONTADO y el total sube
        $totalNuevo = (float) ($data['Total'] ?? 0);
        $totalViejo = (float) ($this->record->Total ?? 0);
        $delta = $totalNuevo - $totalViejo;
        if (($data['TipoCompra'] ?? 'CONTADO') === 'CONTADO' && $delta > 0) {
            $sedeId = $this->record->SedeID ?? auth()->user()->getEffectiveSedeId();
            if ($sedeId) {
                $fondo = FondoSede::withoutGlobalScope('sede')
                    ->lockForUpdate()
                    ->where('SedeID', $sedeId)
                    ->first();
                $saldo = $fondo ? (float) $fondo->SaldoCajaChica : 0;
                if ($saldo < $delta) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo insuficiente en Caja Chica')
                        ->body("Saldo disponible: S/ " . number_format($saldo, 2) . ". Incremento requerido: S/ " . number_format($delta, 2))
                        ->persistent()
                        ->send();
                    $this->halt();
                }
            }
        }

        $data['UsuarioModificacion'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $totalNuevo = (float) ($record->Total ?? 0);
        $totalViejo = $this->totalAnterior ?? 0;
        $delta = $totalNuevo - $totalViejo;

        // Solo ajusta Caja Chica si es CONTADO
        if ($record->TipoCompra === 'CONTADO' && $delta != 0) {
            $sedeId = $record->SedeID ?? auth()->user()->getEffectiveSedeId();
            if ($sedeId) {
                $service = app(FondoSedeService::class);
                try {
                    if ($delta > 0) {
                        $service->registrarEgresoCajaChica($sedeId, $delta, $record->CompraID, auth()->id());
                    } else {
                        $service->inyectarCapitalCajaChica($sedeId, abs($delta), auth()->id(), "Ajuste por edición de compra #{$record->CompraID}");
                    }
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo insuficiente')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();
                    throw $e;
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function ($record) {
                    $record->update(['Activo' => false]);

                    $totalCompra = (float) ($record->Total ?? 0);
                    if ($totalCompra > 0) {
                        $sedeId = $record->SedeID ?? auth()->user()->getEffectiveSedeId();
                        if ($sedeId) {
                            app(FondoSedeService::class)->inyectarCapitalCajaChica(
                                $sedeId,
                                $totalCompra,
                                auth()->id(),
                                "Reversión por eliminación de compra #{$record->CompraID}"
                            );
                        }
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Compra actualizada correctamente';
    }
}
