<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FondoSedeResource\Pages;
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
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class FondoSedeResource extends Resource 
{
    protected static ?string $model = FondoSede::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    
    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $modelLabel = 'Fondo de Sede';

    protected static ?string $pluralModelLabel = 'Fondos de Sedes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('SedeID')
                    ->label('Sede')
                    ->options(fn() => Sede::where('Nombre', 'like', '%Gerencia%')->pluck('Nombre', 'SedeID'))
                    ->default(fn() => optional(Sede::where('Nombre', 'like', '%Gerencia%')->first())->SedeID)
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('Saldo')
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
                    ->money('PEN')
                    ->sortable()
                    ->color('success')
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
                Tables\Actions\Action::make('inyectarCapital')
                    ->label('Inyectar Capital')
                    ->icon('heroicon-m-plus-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->SedeID && stripos(auth()->user()->sede->Nombre, 'Gerencia') !== false)
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
                                ->title('Capital inyectado exitosamente')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFondoSedes::route('/'),
        ];
    }
}
