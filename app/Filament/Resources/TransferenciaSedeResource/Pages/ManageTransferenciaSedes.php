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
                ->after(function (\App\Models\TransferenciaSede $record) {
                    try {
                        \App\Models\User::notificarAdmin(
                            'Nueva remesa / transferencia',
                            'Solicitud pendiente de aprobación',
                            'heroicon-o-truck',
                            $record->EsSolicitudCapital ? $record->SedeDestinoID : $record->SedeOrigenID
                        );
                    } catch (\Exception $e) {
                    }
                })
                ->successNotificationTitle('Remesa enviada con éxito'),

            Actions\Action::make('solicitarTransferenciaSede')
                ->label('Solicitar Transferencia a Sede')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('warning')
                ->visible(fn () =>
                    \App\Filament\Resources\TransferenciaSedeResource::esGerencia()
                )
                ->form([
                    \Filament\Forms\Components\Select::make('SedeDestinoID')
                        ->label('Sede')
                        ->options(\App\Models\Sede::where('Activo', true)->where('Nombre', 'not like', '%Gerencia%')->pluck('Nombre', 'SedeID'))
                        ->required()
                        ->searchable()
                        ->native(false),
                    \Filament\Forms\Components\TextInput::make('Monto')
                        ->label('Monto a solicitar (S/)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('S/'),
                    \Filament\Forms\Components\Textarea::make('Observacion')
                        ->label('Motivo / Observación')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data, FondoSedeService $service) {
                    try {
                        $sedeGerencia = \App\Models\Sede::where('Nombre', 'like', '%Gerencia%')->first();
                        if (!$sedeGerencia) {
                            throw new \Exception('No se encontró la sede de Gerencia.');
                        }

                        $service->crearTransferencia(
                            $sedeGerencia->SedeID,
                            $data['SedeDestinoID'],
                            $data['Monto'],
                            auth()->id(),
                            $data['Observacion'],
                            'CAJA_ABIERTA',
                            'CAJA_ABIERTA',
                            false,
                            true
                        );

                        Notification::make()
                            ->success()
                            ->title('Solicitud enviada')
                            ->body('La sede verá la solicitud en sus remesas pendientes.')
                            ->send();

                        \App\Models\User::notificarAdmin(
                            'Gerencia solicita transferencia',
                            \App\Models\Sede::find($data['SedeDestinoID'])?->Nombre . " — S/ {$data['Monto']}",
                            'heroicon-o-arrow-down-circle',
                            $data['SedeDestinoID']
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->send();
                        throw $e;
                    }
                }),
        ];
    }
}
