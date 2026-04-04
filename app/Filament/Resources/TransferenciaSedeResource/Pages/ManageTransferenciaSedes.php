<?php

namespace App\Filament\Resources\TransferenciaSedeResource\Pages;

use App\Filament\Resources\TransferenciaSedeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use App\Services\FondoSedeService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ManageTransferenciaSedes extends ManageRecords
{
    protected static string $resource = TransferenciaSedeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Transferencia')
                ->visible(fn () => auth()->user()->SedeID && stripos(auth()->user()->sede->Nombre, 'Gerencia') !== false)
                ->using(function (array $data, FondoSedeService $service): \Illuminate\Database\Eloquent\Model {
                    try {
                        $sedeOrigenId = auth()->user()->SedeID;
                        
                        if (!$sedeOrigenId) {
                            throw ValidationException::withMessages([
                                'Sede' => 'Tu usuario no tiene una sede asignada como origen.'
                            ]);
                        }

                        return $service->crearTransferencia(
                            $sedeOrigenId,
                            $data['SedeDestinoID'],
                            $data['Monto'],
                            auth()->id(),
                            $data['Observacion']
                        );
                    } catch (\Exception $e) {
                        // Re-throw to show validation message in the form
                        throw $e;
                    }
                })
                ->successNotificationTitle('Transferencia enviada con éxito')
        ];
    }
}
