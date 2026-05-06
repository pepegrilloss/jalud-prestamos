<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Services\FondoSedeService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['Activo'] = true;
        $data['UsuarioRegistro'] = auth()->id();

        // Calcular subtotal de cada detalle
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

        $totalCompra = floatval($data['Total']);

        // Validar saldo en Caja Chica ANTES de crear la compra
        if ($totalCompra > 0) {
            $sedeId = $data['SedeID'] ?? auth()->user()->SedeID;

            if (auth()->user()->esAdmin() && session('sede_activa')) {
                $sedeId = session('sede_activa');
            }

            if ($sedeId) {
                $fondo = \App\Models\FondoSede::where('SedeID', $sedeId)->first();
                $saldoDisponible = $fondo ? $fondo->SaldoCajaChica : 0;

                if ($saldoDisponible < $totalCompra) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo insuficiente en Caja Chica')
                        ->body("Saldo disponible: S/ " . number_format($saldoDisponible, 2) . ". Monto requerido: S/ " . number_format($totalCompra, 2))
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }

    protected function afterCreate(): void
    {
        // Descontar de Caja Chica
        $record = $this->record;
        $total = floatval($record->Total);

        if ($total > 0) {
            $sedeId = $record->SedeID ?? auth()->user()->SedeID;

            if (auth()->user()->esAdmin() && session('sede_activa')) {
                $sedeId = session('sede_activa');
            }

            if ($sedeId) {
                app(FondoSedeService::class)->registrarEgresoCajaChica(
                    $sedeId,
                    $total,
                    $record->CompraID,
                    auth()->id()
                );
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra registrada correctamente';
    }
}
