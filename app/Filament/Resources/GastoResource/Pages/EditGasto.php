<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use App\Services\DateFieldResolver;
use App\Services\FondoSedeService;
use App\Services\TesoreriaGerenciaService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoResource::class;

    private ?float $totalAnterior = null;

    private string|int|null $origenAnterior = null;

    private string|int|null $origenNuevo = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['UsuarioModificacion'] = auth()->id();
        $this->totalAnterior = (float) ($this->record->Total ?? 0);

        if ($this->record->OrigenTesoreriaTipo) {
            $service = app(TesoreriaGerenciaService::class);
            $this->origenAnterior = $service->referenciaDocumento(
                $this->record->OrigenTesoreriaTipo,
                $this->record->CuentaTesoreriaID
            );
            $this->origenNuevo = $data['OrigenTesoreria'] ?? $this->origenAnterior;
            unset($data['OrigenTesoreria']);
            $data = array_merge($data, $service->descomponerReferencia($this->origenNuevo));
            $data['MetodoGasto'] = 'TESORERIA_GERENCIA';
        } else {
            unset($data['OrigenTesoreria']);
        }

        // Validar saldo si es CAJA CHICA y el total podría subir
        if (($data['MetodoGasto'] ?? '') === 'CAJA CHICA') {
            $detalles = $data['detalles'] ?? [];
            $nuevoTotal = collect($detalles)->sum(fn ($item) => floatval($item['Monto'] ?? 0));
            $delta = $nuevoTotal - $this->totalAnterior;
            if ($delta > 0) {
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
                            ->body('Saldo disponible: S/ '.number_format($saldo, 2).'. Incremento requerido: S/ '.number_format($delta, 2))
                            ->persistent()
                            ->send();
                        $this->halt();
                    }
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $totalNuevo = $record->detalles()->sum('Monto');
        $record->update(['Total' => $totalNuevo]);
        if ($record->OrigenTesoreriaTipo) {
            app(TesoreriaGerenciaService::class)->ajustarEgresoDocumento([
                'OrigenAnterior' => $this->origenAnterior,
                'OrigenNuevo' => $this->origenNuevo,
                'MontoAnterior' => $this->totalAnterior,
                'MontoNuevo' => $totalNuevo,
                'FechaContable' => (DateFieldResolver::getFechaAbierta() ?? now())->toDateString(),
                'TipoDocumento' => MovimientoTesoreria::GASTO,
                'DocumentoID' => $record->GastoID,
                'Concepto' => "Ajuste de gasto #{$record->GastoID}",
                'Observaciones' => $record->Observaciones,
            ], auth()->id());
        } else {
            $this->ajustarCajaChica($record, $totalNuevo);
        }
    }

    private function ajustarCajaChica($record, float $totalNuevo): void
    {
        if ($record->MetodoGasto !== 'CAJA CHICA') {
            return;
        }

        $totalViejo = $this->totalAnterior ?? 0;
        $delta = $totalNuevo - $totalViejo;

        if ($delta == 0) {
            return;
        }

        $sedeId = $record->SedeID ?? auth()->user()->getEffectiveSedeId();
        if (! $sedeId) {
            return;
        }

        $service = app(FondoSedeService::class);
        try {
            if ($delta > 0) {
                $service->registrarEgresoCajaChica($sedeId, $delta, $record->GastoID, auth()->id());
            } else {
                $service->inyectarCapitalCajaChica($sedeId, abs($delta), auth()->id(), "Ajuste por edición de gasto #{$record->GastoID}");
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function ($record) {
                    if ($record->OrigenTesoreriaTipo && (float) $record->Total > 0) {
                        $service = app(TesoreriaGerenciaService::class);
                        $service->revertirEgresoDocumento([
                            'Origen' => $service->referenciaDocumento(
                                $record->OrigenTesoreriaTipo,
                                $record->CuentaTesoreriaID
                            ),
                            'Monto' => $record->Total,
                            'FechaContable' => (DateFieldResolver::getFechaAbierta() ?? now())->toDateString(),
                            'TipoDocumento' => MovimientoTesoreria::GASTO,
                            'DocumentoID' => $record->GastoID,
                            'Concepto' => "Extorno por eliminación de gasto #{$record->GastoID}",
                            'Observaciones' => $record->Observaciones,
                        ], auth()->id());
                    } elseif ($record->MetodoGasto === 'CAJA CHICA' && (float) $record->Total > 0) {
                        $sedeId = $record->SedeID ?? auth()->user()->getEffectiveSedeId();
                        if ($sedeId) {
                            app(FondoSedeService::class)->inyectarCapitalCajaChica(
                                $sedeId,
                                (float) $record->Total,
                                auth()->id(),
                                "Reversión por eliminación de gasto #{$record->GastoID}"
                            );
                        }
                    }

                    $record->update(['Activo' => false]);
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
