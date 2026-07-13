<?php

namespace App\Filament\Resources\AprobacionResolucionResource\Pages;

use App\Filament\Resources\AprobacionResolucionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewAprobacionResolucion extends ViewRecord
{
    protected static string $resource = AprobacionResolucionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Aprobar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Extorno/Resolución')
                ->modalDescription('¿Está seguro de aprobar esta solicitud? Se reflejarán los cambios financieros correspondientes de forma automática y el excedente se marcará como resuelto.')
                ->visible(fn($record) => $record->Estado === 'PENDIENTE' && (auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Super Admin') || auth()->user()->esAdmin()))
                ->action(function ($record) {
                    try {
                        app(\App\Services\ResolucionExcedenteService::class)->aprobar($record, auth()->user());
                    } catch (\Throwable $e) {
                        $message = $e instanceof \Illuminate\Validation\ValidationException
                            ? collect($e->errors())->flatten()->first()
                            : $e->getMessage();

                        Notification::make()
                            ->title('No se pudo aprobar la solicitud')
                            ->body($message)
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Solicitud Aprobada y Ejecutada')
                        ->success()
                        ->send();

                    try {
                        \App\Models\User::notificarAdmin(
                            'Extorno / Devolución aprobada',
                            'Solicitud #' . $record->SolicitudID . ' — S/ ' . number_format((float) $record->MontoAplicar, 2),
                            'heroicon-o-check-circle',
                            $record->SedeID
                        );
                    } catch (\Exception $e) {
                    }
                }),

            Actions\Action::make('Rechazar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn($record) => $record->Estado === 'PENDIENTE' && (auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Super Admin') || auth()->user()->esAdmin()))
                ->action(function ($record) {
                    $record->update(['Estado' => 'RECHAZADA', 'UserAprobadorID' => auth()->id()]);
                    Notification::make()
                        ->title('Solicitud Rechazada')
                        ->success()
                        ->send();

                    try {
                        \App\Models\User::notificarAdmin(
                            'Extorno / Devolución rechazada',
                            'Solicitud #' . $record->SolicitudID . ' — S/ ' . number_format((float) $record->MontoAplicar, 2),
                            'heroicon-o-x-circle',
                            $record->SedeID
                        );
                    } catch (\Exception $e) {
                    }
                }),
        ];
    }
}
