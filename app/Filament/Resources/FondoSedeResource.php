<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FondoSedeResource\Pages;
use App\Models\FondoSede;
use App\Models\Sede;
use App\Models\TransferenciaSede;
use App\Services\FondoSedeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class FondoSedeResource extends Resource 
{
    protected static ?string $model = FondoSede::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    
    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $modelLabel = 'Fondo de Sede';
    protected static ?string $pluralModelLabel = 'Fondos de Sedes';

    public static function shouldRegisterNavigation(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        return parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        return parent::canAccess();
    }

    public static function canCreate(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'gerencia';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        $user = auth()->user();

        // Si es el panel de gerencia (y el usuario tiene acceso), se muestra todo.
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return $query;
        }

        // Si NO es admin, forzar que solo vea su propia sede.
        if (!$user->esAdmin()) {
            if ($user->SedeID) {
                $query->where('SedeID', $user->SedeID);
            } else {
                $query->whereRaw('1 = 0');
            }
            return $query;
        }

        // Si ES admin, pero está en el panel /admin, debe ver solo la sede seleccionada en sesión.
        $sedeId = session('sede_activa');
        if ($sedeId) {
            $query->where('SedeID', $sedeId);
        } else {
            // Si el admin no ha seleccionado ninguna sede en el selector, no mostrar nada.
            $query->whereRaw('1 = 0');
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('SedeID')
                    ->label('Sede')
                    ->options(fn() => Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('Saldo')
                    ->label('Saldo Caja Abierta')
                    ->required()
                    ->numeric()
                    ->default(0.00)
                    ->prefix('S/'),
                Forms\Components\TextInput::make('SaldoCajaChica')
                    ->label('Saldo Caja Chica')
                    ->required()
                    ->numeric()
                    ->default(0.00)
                    ->prefix('S/'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('Saldo')
                    ->label('Caja Abierta')
                    ->money('PEN')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('SaldoCajaChica')
                    ->label('Caja Chica')
                    ->money('PEN')
                    ->sortable()
                    ->color('info')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Inyectar capital a Caja Abierta (solo Gerencia para su propia sede)
                Tables\Actions\Action::make('inyectarCapital')
                    ->label('Inyectar a Caja Abierta')
                    ->icon('heroicon-m-plus-circle')
                    ->color('primary')
                    ->visible(fn (FondoSede $record) => 
                        filament()->getCurrentPanel()?->getId() === 'gerencia' && 
                        stripos($record->sede->Nombre, 'Gerencia') !== false
                    )
                    ->form([
                        Forms\Components\TextInput::make('monto')
                            ->label('Monto a inyectar (S/)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\Textarea::make('observacion')
                            ->label('Observación')
                            ->required()
                            ->default('Ingreso de capital inicial'),
                    ])
                    ->action(function (FondoSede $record, array $data, FondoSedeService $service) {
                        try {
                            $service->inyectarCapital(
                                $record->SedeID,
                                $data['monto'],
                                auth()->id(),
                                $data['observacion']
                            );

                            Notification::make()
                                ->title('Capital inyectado a Caja Abierta exitosamente')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al inyectar capital')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Solicitar traslado interno (Caja Abierta ↔ Caja Chica) — pendiente de aprobación por Gerencia
                Tables\Actions\Action::make('solicitarTrasladoInterno')
                    ->label('Solicitar Traslado')
                    ->icon('heroicon-m-arrows-right-left')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('direccion')
                            ->label('Dirección del Traslado')
                            ->options([
                                'CA_A_CC' => 'Caja Abierta → Caja Chica',
                                'CC_A_CA' => 'Caja Chica → Caja Abierta',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('monto')
                            ->label('Monto a trasladar (S/)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\Textarea::make('observacion')
                            ->label('Motivo / Observación')
                            ->required()
                            ->default('Solicitud de traslado interno'),
                    ])
                    ->action(function (FondoSede $record, array $data, FondoSedeService $service) {
                        try {
                            $cuentaOrigen = $data['direccion'] === 'CA_A_CC' ? 'CAJA_ABIERTA' : 'CAJA_CHICA';
                            $cuentaDestino = $data['direccion'] === 'CA_A_CC' ? 'CAJA_CHICA' : 'CAJA_ABIERTA';

                            $service->crearTransferencia(
                                $record->SedeID,
                                $record->SedeID,
                                $data['monto'],
                                auth()->id(),
                                $data['observacion'],
                                $cuentaOrigen,
                                $cuentaDestino
                            );

                            Notification::make()
                                ->success()
                                ->title('Solicitud de traslado enviada a Gerencia')
                                ->body('La solicitud quedará pendiente hasta que Gerencia la apruebe.')
                                ->send();

                            try {
                                $direccion = $data['direccion'] === 'CA_A_CC' ? 'Caja Abierta → Caja Chica' : 'Caja Chica → Caja Abierta';
                                \App\Models\User::notificarAdmin(
                                    'Solicitud de traslado interno',
                                    "{$record->sede->Nombre} — {$direccion} — S/ {$data['monto']}",
                                    'heroicon-o-arrows-right-left'
                                );
                            } catch (\Exception $e) {
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al solicitar traslado')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Solicitar capital a Gerencia — pendiente de aprobación
                Tables\Actions\Action::make('solicitarCapitalGerencia')
                    ->label('Solicitar Capital')
                    ->icon('heroicon-o-building-library')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('monto')
                            ->label('Monto a solicitar (S/)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\Textarea::make('observacion')
                            ->label('Motivo / Observación')
                            ->required()
                            ->default('Solicitud de capital a Gerencia'),
                    ])
                    ->action(function (FondoSede $record, array $data, FondoSedeService $service) {
                        try {
                            $sedeGerencia = Sede::where('Nombre', 'like', '%Gerencia%')->first();
                            if (!$sedeGerencia) {
                                throw new \Exception('No se encontró la sede de Gerencia.');
                            }

                            $service->crearTransferencia(
                                $record->SedeID,
                                $sedeGerencia->SedeID,
                                $data['monto'],
                                auth()->id(),
                                $data['observacion'],
                                'CAJA_ABIERTA',
                                'CAJA_ABIERTA',
                                true
                            );

                            Notification::make()
                                ->success()
                                ->title('Solicitud de capital enviada a Gerencia')
                                ->body('La solicitud quedará pendiente hasta que Gerencia la apruebe.')
                                ->send();

                            try {
                                \App\Models\User::notificarAdmin(
                                    'Solicitud de capital',
                                    "{$record->sede->Nombre} solicita S/ {$data['monto']} a Gerencia",
                                    'heroicon-o-building-library'
                                );
                            } catch (\Exception $e) {
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al solicitar capital')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo Fondo de Sede')
                    ->visible(fn() => filament()->getCurrentPanel()?->getId() === 'gerencia'),
                Tables\Actions\Action::make('inyectarCapitalGeneral')
                    ->label('Inyectar Capital Inicial')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->visible(fn() => filament()->getCurrentPanel()?->getId() === 'gerencia')
                    ->form([
                        \Filament\Forms\Components\Select::make('sede_id')
                            ->label('Sede Destino (Solo Gerencia)')
                            ->options(\App\Models\Sede::where('Activo', true)->where('Nombre', 'like', '%Gerencia%')->pluck('Nombre', 'SedeID'))
                            ->default(fn() => \App\Models\Sede::where('Activo', true)->where('Nombre', 'like', '%Gerencia%')->value('SedeID'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('monto')
                            ->label('Monto a inyectar (S/)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        \Filament\Forms\Components\Textarea::make('observacion')
                            ->label('Observación')
                            ->required()
                            ->default('Ingreso de capital inicial a la sede'),
                    ])
                    ->action(function (array $data, \App\Services\FondoSedeService $service) {
                        try {
                            $service->inyectarCapital(
                                $data['sede_id'],
                                $data['monto'],
                                auth()->id(),
                                $data['observacion']
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Capital inyectado exitosamente')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al inyectar capital')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('inyectarCapitalGeneralEmpty')
                    ->label('Inyectar Capital Inicial')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->visible(fn() => filament()->getCurrentPanel()?->getId() === 'gerencia')
                    ->form([
                        \Filament\Forms\Components\Select::make('sede_id')
                            ->label('Sede Destino (Solo Gerencia)')
                            ->options(\App\Models\Sede::where('Activo', true)->where('Nombre', 'like', '%Gerencia%')->pluck('Nombre', 'SedeID'))
                            ->default(fn() => \App\Models\Sede::where('Activo', true)->where('Nombre', 'like', '%Gerencia%')->value('SedeID'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('monto')
                            ->label('Monto a inyectar (S/)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        \Filament\Forms\Components\Textarea::make('observacion')
                            ->label('Observación')
                            ->required()
                            ->default('Ingreso de capital inicial a la sede'),
                    ])
                    ->action(function (array $data, \App\Services\FondoSedeService $service) {
                        try {
                            $service->inyectarCapital(
                                $data['sede_id'],
                                $data['monto'],
                                auth()->id(),
                                $data['observacion']
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Capital inyectado exitosamente')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al inyectar capital')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFondoSedes::route('/'),
        ];
    }
}
