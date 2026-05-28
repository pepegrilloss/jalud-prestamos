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

    public static function getNavigationBadge(): ?string
    {
        if (!self::esGerencia()) return null;
        $count = static::getModel()::where('Estado', 'PENDIENTE')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

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
                        $userSedeId = auth()->user()->getEffectiveSedeId();
                        
                        // Si estamos en el panel de gerencia, el origen es la sede de Gerencia
                        if (filament()->getCurrentPanel()?->getId() === 'gerencia') {
                            $sedeGerencia = Sede::where('Nombre', 'like', '%Gerencia%')->first();
                            if ($sedeGerencia) {
                                $userSedeId = $sedeGerencia->SedeID;
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
                Forms\Components\FileUpload::make('VoucherImagen')
                    ->label('Voucher del Depósito')
                    ->image()
                    ->directory('fondos/vouchers')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Sube el comprobante del depósito (opcional)')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                Tables\Columns\TextColumn::make('MontoAprobado')
                    ->label('Aprobado')
                    ->money('PEN')
                    ->visible(fn (?TransferenciaSede $record) => $record?->EsSolicitudCapital ?? false)
                    ->color('success'),
                Tables\Columns\TextColumn::make('Tipo')
                    ->label('Tipo')
                    ->getStateUsing(fn (?TransferenciaSede $record) => match(true) {
                        ($record?->EsSolicitudGerencia ?? false) => 'Solicitud Gerencia',
                        ($record?->EsSolicitudCapital ?? false) => 'Solicitud Capital',
                        default => 'Remesa',
                    })
                    ->badge()
                    ->color(fn (?TransferenciaSede $record) => match(true) {
                        ($record?->EsSolicitudGerencia ?? false) => 'warning',
                        ($record?->EsSolicitudCapital ?? false) => 'info',
                        default => 'gray',
                    }),
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

            Tables\Filters\SelectFilter::make('SedeID')
                ->label('Sede')
                ->options(fn() => \App\Models\Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                ->visible(fn() => auth()->user()->isPrivileged() && !session('sede_activa')),
            
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn() => self::esGerencia()),
                Tables\Actions\Action::make('Aceptar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form(function (TransferenciaSede $record) {
                        if (!$record->EsSolicitudCapital) {
                            return [];
                        }
                        return [
                            Forms\Components\TextInput::make('montoAprobado')
                                ->label('Monto a Aprobar (S/)')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->maxValue((float) $record->Monto)
                                ->default((float) $record->Monto)
                                ->helperText('Puede aprobar el monto total o uno parcial. Máx: S/' . number_format((float) $record->Monto, 2)),
                        ];
                    })
                    ->modalHeading(function (TransferenciaSede $record) {
                        return $record->EsSolicitudCapital
                            ? 'Aprobar Solicitud de Capital'
                            : 'Aceptar Transferencia';
                    })
                    ->visible(function (TransferenciaSede $record) {
                        if ($record->Estado !== 'PENDIENTE') return false;
                        return self::esGerencia();
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service, array $data = []) {
                        try {
                            $montoAprobado = $record->EsSolicitudCapital ? ($data['montoAprobado'] ?? null) : null;
                            $service->aceptarTransferencia($record, auth()->id(), $montoAprobado);
                            Notification::make()
                                ->success()
                                ->title($record->EsSolicitudCapital ? 'Capital aprobado' : 'Transferencia aceptada')
                                ->send();

                            User::notificarAdmin(
                                $record->EsSolicitudCapital ? 'Capital aprobado' : 'Transferencia aceptada',
                                $record->EsSolicitudCapital
                                    ? "S/ {$record->MontoAprobado} a {$record->sedeOrigen->Nombre}"
                                    : "S/ {$record->Monto} — {$record->sedeOrigen->Nombre} → {$record->sedeDestino->Nombre}",
                                'heroicon-o-check-circle',
                                $record->EsSolicitudCapital ? $record->SedeOrigenID : $record->SedeDestinoID
                            );
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al aceptar')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('Transferir')
                    ->label('Transferir a Gerencia')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Transferencia a Gerencia')
                    ->modalDescription(fn (TransferenciaSede $record) => "Transferir S/ " . number_format((float) $record->Monto, 2) . " a Gerencia. Esta acción es inmediata y no requiere aprobación adicional.")
                    ->visible(function (TransferenciaSede $record) {
                        if ($record->Estado !== 'PENDIENTE') return false;
                        if (!$record->EsSolicitudGerencia) return false;
                        $sedeId = auth()->user()->getEffectiveSedeId();
                        return $sedeId === $record->SedeDestinoID;
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service) {
                        try {
                            $service->ejecutarTransferenciaSolicitada($record, auth()->id());
                            Notification::make()
                                ->success()
                                ->title('Transferencia realizada')
                                ->body('El dinero ha sido transferido a Gerencia exitosamente.')
                                ->send();

                            User::notificarAdmin(
                                'Transferencia ejecutada',
                                "{$record->sedeDestino->Nombre} transfirió S/ {$record->Monto} a Gerencia",
                                'heroicon-o-banknotes',
                                $record->SedeDestinoID
                            );
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al transferir')
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
                                'heroicon-o-x-circle',
                                $record->SedeOrigenID
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
            'view' => Pages\ViewTransferenciaSede::route('/{record}'),
        ];
    }

    public static function esGerencia(): bool
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
