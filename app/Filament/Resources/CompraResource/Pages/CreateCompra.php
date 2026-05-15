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
            $sedeId = auth()->user()->getEffectiveSedeId();

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
            $sedeId = auth()->user()->getEffectiveSedeId();

            if ($sedeId) {
                app(FondoSedeService::class)->registrarEgresoCajaChica(
                    $sedeId,
                    $total,
                    $record->CompraID,
                    auth()->id()
                );
            }
        }

        // Notificar a administradores en la campanita
        try {
            $sede = $record->sede?->Nombre ?? 'N/A';
            $proveedor = $record->proveedor?->Nombre ?? 'N/A';
            $monto = number_format($record->Total, 2);
            $usuario = auth()->user()->name ?? 'Sistema';
            
            \App\Models\User::notificarAdmin(
                "Compra registrada — S/ {$monto}",
                "Proveedor: {$proveedor} en {$sede} (por {$usuario})",
                'heroicon-o-shopping-cart',
                $record->SedeID
            );
        } catch (\Exception $e) {
            \Log::warning('No se pudo enviar notificación de compra a admins', ['error' => $e->getMessage()]);
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
