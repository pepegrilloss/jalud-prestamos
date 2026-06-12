<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Services\FondoSedeService;
use App\Models\Sede;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateGasto extends CreateRecord
{
    protected static string $resource = GastoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['Activo'] = true;
        $data['Total'] = 0;
        $data['UsuarioRegistro'] = auth()->id();

        // Calcular total anticipado desde los detalles del formulario
        $detalles = $data['detalles'] ?? [];
        $totalAnticipado = collect($detalles)->sum(fn($item) => floatval($item['Monto'] ?? 0));

        // Si el método de gasto es CAJA CHICA, validar saldo ANTES de crear (con lockForUpdate)
        if (($data['MetodoGasto'] ?? '') === 'CAJA CHICA' && $totalAnticipado > 0) {
            $sedeId = auth()->user()->getEffectiveSedeId();

            if ($sedeId) {
                $fondo = \App\Models\FondoSede::withoutGlobalScope('sede')
                    ->lockForUpdate()
                    ->where('SedeID', $sedeId)
                    ->first();
                $saldoDisponible = $fondo ? (float) $fondo->SaldoCajaChica : 0;

                if ($saldoDisponible < $totalAnticipado) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo insuficiente en Caja Chica')
                        ->body("Saldo disponible: S/ " . number_format($saldoDisponible, 2) . ". Monto requerido: S/ " . number_format($totalAnticipado, 2))
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
        // Recalcular total desde los detalles guardados
        $record = $this->record;
        $total = $record->detalles()->sum('Monto');
        $record->update(['Total' => $total]);

        // Descontar de Caja Chica si el método es CAJA CHICA
        if ($record->MetodoGasto === 'CAJA CHICA' && $total > 0) {
            $sedeId = auth()->user()->getEffectiveSedeId();

            if ($sedeId) {
                try {
                    app(FondoSedeService::class)->registrarEgresoCajaChica(
                        $sedeId,
                        $total,
                        $record->GastoID,
                        auth()->id()
                    );
                } catch (\Illuminate\Validation\ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo insuficiente en Caja Chica')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();
                    throw $e;
                }
            }
        }

        // Notificar a administradores en la campanita
        try {
            $sede = $record->sede?->Nombre ?? 'N/A';
            $motivo = $record->motivo?->Nombre ?? 'N/A';
            $monto = number_format($record->Total, 2);
            $usuario = auth()->user()->name ?? 'Sistema';
            
            \App\Models\User::notificarAdmin(
                "Gasto registrado — S/ {$monto}",
                "{$motivo} en {$sede} (por {$usuario})",
                'heroicon-o-banknotes',
                $record->SedeID
            );
        } catch (\Exception $e) {
            \Log::warning('No se pudo enviar notificación de gasto a admins', ['error' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Gasto registrado correctamente';
    }
}
