<?php

namespace App\Filament\Resources;

use App\Models\AperturaCierreDia;
use App\Services\AperturaCierreDiaLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;

use App\Models\Sede;
class AperturaCierreDiaResource extends Resource
{
    protected static ?string $model = AperturaCierreDia::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Apertura/Cierre Día';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('Fecha')
                    ->required()
                    ->default(today())
                    ->disabled(fn(string $operation): bool => $operation === 'edit')
                    ->maxDate(today())
                    ->native(false)
                    ->unique(AperturaCierreDia::class, 'Fecha', ignoreRecord: true),

                Forms\Components\Select::make('EstadoDia')
                    ->options([
                        'ABIERTO' => 'Abierto',
                        'CERRADO' => 'Cerrado',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state === 'ABIERTO') {
                            $set('FechaApertura', now()->toDateTimeString());
                            $set('UsuarioAperturaID', auth()->id());
                        } else {
                            $set('FechaApertura', null);
                            $set('UsuarioAperturaID', null);
                        }
                    })
                    ->disabled(fn(string $operation): bool => $operation === 'edit'),

                Forms\Components\DateTimePicker::make('FechaApertura')
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'ABIERTO')
                    ->native(false)
                    ->dehydrated()
                    ->disabled(),

                Forms\Components\DateTimePicker::make('FechaCierre')
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'CERRADO')
                    ->native(false)
                    ->dehydrated()
                    ->disabled(),

                Forms\Components\Select::make('UsuarioAperturaID')
                    ->relationship('usuarioApertura', 'name')
                    ->dehydrated()
                    ->disabled()
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'ABIERTO'),

                Forms\Components\Select::make('UsuarioCierreID')
                    ->relationship('usuarioCierre', 'name')
                    ->dehydrated()
                    ->disabled()
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'CERRADO'),

                Forms\Components\Textarea::make('Observaciones')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('EstadoDia')
                    ->colors([
                        'success' => 'ABIERTO',
                        'danger' => 'CERRADO',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('usuarioApertura.name')
                    ->label('Usuario Apertura')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('usuarioCierre.name')
                    ->label('Usuario Cierre')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('FechaApertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaCierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn() => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('EstadoDia')
                    ->options([
                        'ABIERTO' => 'Abierto',
                        'CERRADO' => 'Cerrado',
                    ]),
            ])
            ->actions([
                // VER OBSERVACIONES
                Tables\Actions\Action::make('verObservaciones')
                    ->label('Observaciones')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modal()
                    ->modalHeading(fn(AperturaCierreDia $record) => "Observaciones - {$record->Fecha->format('d/m/Y')}")
                    ->modalContent(fn(AperturaCierreDia $record) => view('components.modal-observaciones', ['observaciones' => $record->Observaciones ?? 'Sin observaciones']))
                    ->modalSubmitActionLabel('Cerrar'),

                // CERRAR DÍA - Solo visible cuando está ABIERTO
                Tables\Actions\Action::make('cerrarDia')
                    ->label('Cerrar Día')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn(AperturaCierreDia $record) => $record->EstadoDia === 'ABIERTO')
                    ->action(function (AperturaCierreDia $record) {
                        $logger = new AperturaCierreDiaLogger();

                        try {
                            $logger->info('[APERTURA_CIERRE] Cerrando día', [
                                'record_id' => $record->AperturaCierreDiaID,
                                'fecha' => $record->Fecha->format('d/m/Y'),
                            ]);

                            DB::transaction(function () use ($record, $logger) {
                                $recordLocked = AperturaCierreDia::lockForUpdate()
                                    ->find($record->AperturaCierreDiaID);

                                if ($recordLocked->EstadoDia !== 'ABIERTO') {
                                    throw new \Exception('El día ya está cerrado.');
                                }

                                $recordLocked->update([
                                    'EstadoDia' => 'CERRADO',
                                    'FechaCierre' => now(),
                                    'UsuarioCierreID' => auth()->id(),
                                ]);

                                $logger->success('[APERTURA_CIERRE] Día cerrado exitosamente');
                            });

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Día cerrado')
                                ->body("El día {$record->Fecha->format('d/m/Y')} ha sido cerrado.")
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            $logger->error('[APERTURA_CIERRE] Error al cerrar día', [
                                'error' => $e->getMessage(),
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al cerrar')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                // ABRIR FECHA - Solo visible cuando está CERRADO
                Tables\Actions\Action::make('abrirFecha')
                    ->label('Abrir Fecha')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn(AperturaCierreDia $record) => $record->EstadoDia === 'CERRADO')
                    ->action(function (AperturaCierreDia $record) {
                        $logger = new AperturaCierreDiaLogger();

                        try {
                            $logger->info('[APERTURA_CIERRE] Iniciando acción abrirFecha', [
                                'record_id' => $record->AperturaCierreDiaID,
                                'fecha' => $record->Fecha->format('d/m/Y'),
                            ]);

                            DB::transaction(function () use ($record, $logger) {
                                $recordLocked = AperturaCierreDia::lockForUpdate()
                                    ->find($record->AperturaCierreDiaID);

                                if ($recordLocked->EstadoDia !== 'CERRADO') {
                                    throw new \Exception('El estado del registro cambió. Por favor, recarga la página.');
                                }

                                $diaAbierto = AperturaCierreDia::lockForUpdate()
                                    ->where('EstadoDia', 'ABIERTO')
                                    ->where('AperturaCierreDiaID', '!=', $record->AperturaCierreDiaID)
                                    ->first();

                                if ($diaAbierto) {
                                    throw new \Exception("Ya hay un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}");
                                }

                                $recordLocked->update([
                                    'EstadoDia' => 'ABIERTO',
                                    'FechaCierre' => null,
                                    'UsuarioCierreID' => null,
                                    'FechaApertura' => now(),
                                    'UsuarioAperturaID' => auth()->id(),
                                ]);

                                // Limpiar FechaCierre de todos los registros del día
                                $record->reabrirDia();

                                $logger->success('[APERTURA_CIERRE] Fecha abierta exitosamente');
                            });

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Fecha abierta')
                                ->body("La fecha {$record->Fecha->format('d/m/Y')} ha sido abierta para operaciones.")
                                ->persistent()
                                ->send();

                        } catch (UniqueConstraintViolationException $e) {
                            $logger->error('[APERTURA_CIERRE] Constraint violation en abrirFecha');

                            $diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();

                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede abrir')
                                ->body($diaAbierto
                                    ? "Ya existe un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}. Debe cerrarlo primero."
                                    : "No se puede abrir múltiples días simultáneamente.")
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            $logger->error('[APERTURA_CIERRE] Error en abrirFecha', [
                                'error' => $e->getMessage(),
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede abrir')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('Fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AperturaCierreDiaResource\Pages\GestionarAperturaCierre::route('/'),
        ];
    }

    public static function cerrarDia(AperturaCierreDia $record): void
    {
        $record->cerrarDia();
    }

    public static function reabrirDia(AperturaCierreDia $record): void
    {
        $record->reabrirDia();
    }
}