<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Services\FondoSedeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoResource::class;

    private ?float $totalAnterior = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['UsuarioModificacion'] = auth()->id();
        $this->totalAnterior = (float) ($this->record->Total ?? 0);
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $totalNuevo = $record->detalles()->sum('Monto');
        $record->update(['Total' => $totalNuevo]);
        $this->ajustarCajaChica($record, $totalNuevo);
    }

    private function ajustarCajaChica($record, float $totalNuevo): void
    {
        if ($record->MetodoGasto !== 'CAJA CHICA') return;

        $totalViejo = $this->totalAnterior ?? 0;
        $delta = $totalNuevo - $totalViejo;

        if ($delta == 0) return;

        $sedeId = $record->SedeID ?? auth()->user()->SedeID;
        if (auth()->user()->esAdmin() && session('sede_activa')) {
            $sedeId = session('sede_activa');
        }
        if (!$sedeId) return;

        $service = app(FondoSedeService::class);
        if ($delta > 0) {
            $service->registrarEgresoCajaChica($sedeId, $delta, $record->GastoID, auth()->id());
        } else {
            $service->inyectarCapitalCajaChica($sedeId, abs($delta), auth()->id(), "Ajuste por edición de gasto #{$record->GastoID}");
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function ($record) {
                    $record->update(['Activo' => false]);

                    if ($record->MetodoGasto === 'CAJA CHICA' && (float) $record->Total > 0) {
                        $sedeId = $record->SedeID ?? auth()->user()->SedeID;
                        if (auth()->user()->esAdmin() && session('sede_activa')) {
                            $sedeId = session('sede_activa');
                        }
                        if ($sedeId) {
                            app(FondoSedeService::class)->inyectarCapitalCajaChica(
                                $sedeId,
                                (float) $record->Total,
                                auth()->id(),
                                "Reversión por eliminación de gasto #{$record->GastoID}"
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
        return 'Gasto actualizado correctamente';
    }
}
