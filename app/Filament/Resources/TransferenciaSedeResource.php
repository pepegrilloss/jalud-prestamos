<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferenciaSedeResource\Pages;
use App\Models\TransferenciaSede;
use App\Models\FondoSede;
use App\Models\Sede;
use App\Services\FondoSedeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Traits\BelongsToSede; // To filter by current Sede if necessary

class TransferenciaSedeResource extends Resource
{
    protected static ?string $model = TransferenciaSede::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $modelLabel = 'Transferencia a Sede';

    protected static ?string $pluralModelLabel = 'Transferencias entre Sedes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('SedeDestinoID')
                    ->label('Sede Destino')
                    ->options(function () {
                        // All sedes except the user's current Sede
                        $userSedeId = auth()->user()->SedeID;
                        return Sede::where('SedeID', '!=', $userSedeId)
                            ->where('Activo', true)
                            ->pluck('Nombre', 'SedeID');
                    })
                    ->required(),
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
                // Filter transfers matching their Sede (either Origin or Dest)
                
                // Otherwise only see transfers matching their Sede (either Origin or Dest)
                $sedeId = auth()->user()->SedeID;
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
                Tables\Columns\TextColumn::make('sedeDestino.Nombre')
                    ->label('Destino')
                    ->sortable(),
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
                        // Visible only if pending and user belongs to dest Sede
                        if ($record->Estado !== 'PENDIENTE') return false;
                        return auth()->user()->SedeID === $record->SedeDestinoID;
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service) {
                        try {
                            $service->aceptarTransferencia($record, auth()->id());
                            Notification::make()
                                ->success()
                                ->title('Transferencia aceptada')
                                ->send();
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
                        // Visible only if pending and user belongs to dest Sede
                        if ($record->Estado !== 'PENDIENTE') return false;
                        return auth()->user()->SedeID === $record->SedeDestinoID;
                    })
                    ->action(function (TransferenciaSede $record, FondoSedeService $service) {
                        try {
                            $service->rechazarTransferencia($record, auth()->id());
                            Notification::make()
                                ->warning()
                                ->title('Transferencia rechazada')
                                ->body('Los fondos han sido devueltos a la sede de origen.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al rechazar')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTransferenciaSedes::route('/'),
        ];
    }
}
