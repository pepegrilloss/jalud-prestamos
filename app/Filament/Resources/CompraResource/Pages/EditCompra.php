<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Services\FondoSedeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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

        if (empty($data['MontoIGV']) || floatval($data['MontoIGV']) == 0) {
            $data['MontoIGV'] = round($subtotalBase * 0.18, 2);
        }

        if (empty($data['Total']) || floatval($data['Total']) == 0) {
            $data['Total'] = floatval($data['SubtotalBase']) + floatval($data['MontoIGV']);
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

        if ($delta != 0) {
            $sedeId = $record->SedeID ?? auth()->user()->SedeID;
            if (auth()->user()->esAdmin() && session('sede_activa')) {
                $sedeId = session('sede_activa');
            }
            if ($sedeId) {
                $service = app(FondoSedeService::class);
                if ($delta > 0) {
                    $service->registrarEgresoCajaChica($sedeId, $delta, $record->CompraID, auth()->id());
                } else {
                    $service->inyectarCapitalCajaChica($sedeId, abs($delta), auth()->id(), "Ajuste por edición de compra #{$record->CompraID}");
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
                        $sedeId = $record->SedeID ?? auth()->user()->SedeID;
                        if (auth()->user()->esAdmin() && session('sede_activa')) {
                            $sedeId = session('sede_activa');
                        }
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
