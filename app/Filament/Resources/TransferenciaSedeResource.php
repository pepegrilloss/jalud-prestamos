<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferenciaSedeResource\Pages;
use App\Models\TransferenciaSede;
use App\Models\FondoSede;
use App\Models\Sede;
use App\Models\User;
use App\Services\FondoSedeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class TransferenciaSedeResource extends Resource
{
    protected static ?string $model = TransferenciaSede::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $modelLabel = 'Remesa / Transferencia';
    protected static ?string $pluralModelLabel = 'Remesas y Transferencias';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('SedeDestinoID')
                    ->label('Sede Destino')
                    ->options(function () {
                        $userSedeId = auth()->user()->SedeID;
                        
                        // Si estamos en el panel de gerencia, el origen es la sede de Gerencia
                        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
                            $sedeGerencia = Sede::where('Nombre', 'like', '%Gerencia%')->first();
                            if ($sedeGerencia) {
                                $userSedeId = $sedeGerencia->SedeID;
                            }
                        } else {
                            if (auth()->user()->esAdmin() && session('sede_activa')) {
                                $userSedeId = session('sede_activa');
                            }
                        }

                        return Sede::where('SedeID', '!=', $userSedeId)
                            ->where('Activo', true)
                            ->pluck('Nombre', 'SedeID');
                    })
                    ->required(),
                Forms\Components\Select::make('CuentaOrigen')
                    ->label('Cuenta Origen')
                    ->options([
                        'CAJA_ABIERTA' => 'Caja Abierta',
                        'CAJA_CHICA' => 'Caja Chica',
                    ])
                    ->default('CAJA_ABIERTA')
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('CuentaDestino')
                    ->label('Cuenta Destino')
                    ->options([
                        'CAJA_ABIERTA' => 'Caja Abierta',
                        'CAJA_CHICA' => 'Caja Chica',
                    ])
                    ->default('CAJA_ABIERTA')
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('Monto')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('S/'),
                Forms\Components\Textarea::make('Observacion')
                    ->label('Motivo / Observación')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
                    return $query;
                }
                $sedeId = auth()->user()->SedeID;
                if (auth()->user()->esAdmin() && session('sede_activa')) {
                    $sedeId = session('sede_activa');
                }
                if ($sedeId) {
                    $query->where(function($q) use ($sedeId) {
                        $q->where('SedeOrigenID', $sedeId)
                          ->orWhere('SedeDestinoID', $sedeId);
                    });
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('TransferenciaID')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sedeOrigen.Nombre')
                    ->label('Origen')
                    ->sortable(),
                Tables\Columns\TextColumn::make('CuentaOrigen')
                    ->label('Cta. Origen')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'CAJA_ABIERTA' => 'Caja Abierta',
                        'CAJA_CHICA' => 'Caja Chica',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'CAJA_CHICA' ? 'info' : 'primary'),
                Tables\Columns\TextColumn::make('sedeDestino.Nombre')
                    ->label('Destino')
                    ->sortable(),
                Tables\Columns\TextColumn::make('CuentaDestino')
                    ->label('Cta. Destino')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'CAJA_ABIERTA' => 'Caja Abierta',
                        'CAJA_CHICA' => 'Caja Chica',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'CAJA_CHICA' ? 'info' : 'primary'),
                Tables\Columns\TextColumn::make('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDIENTE' => 'warning',
                        'ACEPTADO' => 'success',
                        'RECHAZADO' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('FechaTransferencia')
                    ->label('Enviado')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usuarioOrigen.name')
                    ->label('Quien envía'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'ACEPTADO' => 'Aceptado',
                        'RECHAZADO' => 'Rechazado',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('Aceptar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (TransferenciaSede $record) {
                        if ($record->Estado !== 'PENDIENTE') return false;
                        return self::esGerencia();
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service) {
                        try {
                            $service->aceptarTransferencia($record, auth()->id());
                            Notification::make()
                                ->success()
                                ->title('Transferencia aceptada')
                                ->send();

                            User::notificarAdmin(
                                'Transferencia aceptada',
                                "S/ {$record->Monto} — {$record->sedeOrigen->Nombre} → {$record->sedeDestino->Nombre}",
                                'heroicon-o-check-circle'
                            );
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al aceptar')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(function (TransferenciaSede $record) {
                        if ($record->Estado !== 'PENDIENTE') return false;
                        return self::esGerencia();
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service) {
                        try {
                            $service->rechazarTransferencia($record, auth()->id());
                            Notification::make()
                                ->warning()
                                ->title('Transferencia rechazada')
                                ->body('Los fondos han sido devueltos a la sede de origen.')
                                ->send();

                            User::notificarAdmin(
                                'Transferencia rechazada',
                                "S/ {$record->Monto} — {$record->sedeOrigen->Nombre} → {$record->sedeDestino->Nombre}",
                                'heroicon-o-x-circle'
                            );
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al rechazar')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('TransferenciaID', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTransferenciaSedes::route('/'),
        ];
    }

    private static function esGerencia(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
            return true;
        }
        $sedeActiva = session('sede_activa');
        if ($sedeActiva) {
            $sede = Sede::find($sedeActiva);
            return $sede && stripos($sede->Nombre, 'Gerencia') !== false;
        }
        return false;
    }
}
