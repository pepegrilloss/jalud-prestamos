<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogResource\Pages;
use App\Models\Log;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LogResource extends Resource
{
    protected static ?string $model = Log::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Auditoría';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationGroupSort = 10;
    protected static ?string $modelLabel = 'Log';
    protected static ?string $pluralModelLabel = 'Logs';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Log')
                    ->schema([
                        Forms\Components\TextInput::make('user_id')
                            ->label('Usuario ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('accion')
                            ->label('Acción')
                            ->disabled(),

                        Forms\Components\TextInput::make('modelo')
                            ->label('Modelo')
                            ->disabled(),

                        Forms\Components\TextInput::make('modelo_id')
                            ->label('ID del Modelo')
                            ->disabled(),

                        Forms\Components\Textarea::make('old_values')
                            ->label('Valores Anteriores')
                            ->disabled(),

                        Forms\Components\Textarea::make('new_values')
                            ->label('Valores Nuevos')
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('Dirección IP')
                            ->disabled(),

                        Forms\Components\TextInput::make('machine_name')
                            ->label('Nombre de Máquina')
                            ->disabled(),

                        Forms\Components\TextInput::make('platform')
                            ->label('Plataforma')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Fecha y Hora')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('accion')
                    ->label('Acción')
                    ->color(fn (string $state) => match ($state) {
                        'CREAR' => 'success',
                        'ACTUALIZAR' => 'info',
                        'ELIMINAR' => 'danger',
                        'LOGIN' => 'success',
                        'LOGOUT' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('modelo')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('modelo_id')
                    ->label('ID Modelo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),

                Tables\Columns\TextColumn::make('machine_name')
                    ->label('Máquina')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('accion')
                    ->label('Acción')
                    ->options([
                        'CREAR' => 'Crear',
                        'ACTUALIZAR' => 'Actualizar',
                        'ELIMINAR' => 'Eliminar',
                        'LOGIN' => 'Login',
                        'LOGOUT' => 'Logout',
                    ]),

                Tables\Filters\SelectFilter::make('modelo')
                    ->label('Modelo')
                    ->options(fn () => \App\Models\Log::distinct()->pluck('modelo', 'modelo')->toArray()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogs::route('/'),
            'view' => Pages\ViewLog::route('/{record}'),
        ];
    }
}
