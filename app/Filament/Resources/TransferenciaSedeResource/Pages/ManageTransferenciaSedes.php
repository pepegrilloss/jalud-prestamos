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
                ->label('Nueva Remesa / Transferencia')
                ->using(function (array $data, FondoSedeService $service): \Illuminate\Database\Eloquent\Model {
                    try {
                        $sedeOrigenId = auth()->user()->SedeID;
                        
                        // Si estamos en el panel de gerencia, forzar origen como Gerencia
                        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
                            $sedeGerencia = \App\Models\Sede::where('Nombre', 'like', '%Gerencia%')->first();
                            if ($sedeGerencia) {
                                $sedeOrigenId = $sedeGerencia->SedeID;
                            }
                        } else {
                            if (auth()->user()->esAdmin() && session('sede_activa')) {
                                $sedeOrigenId = session('sede_activa');
                            }
                        }
                        
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
                            $data['Observacion'],
                            $data['CuentaOrigen'] ?? 'CAJA_ABIERTA',
                            $data['CuentaDestino'] ?? 'CAJA_ABIERTA'
                        );
                    } catch (\Exception $e) {
                        throw $e;
                    }
                })
                ->successNotificationTitle('Remesa enviada con éxito')
        ];
    }
}
