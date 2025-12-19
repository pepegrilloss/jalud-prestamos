<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\NivelAprobacion;
use App\Models\UserNivelAprobacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('username')
                            ->label('Usuario')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Solo letras, números, guiones y guiones bajos'),

                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->maxLength(255)
                            ->revealable(),

                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->label('Roles'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nivelAprobacionActivo.nivelAprobacion.Nombre')
                    ->label('Nivel de Aprobación')
                    ->badge()
                    ->color('success')
                    ->placeholder('Sin asignar')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordUrl(null)
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('asignarNivel')
                    ->label('Asignar Nivel')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\Select::make('NivelAprobacionID')
                            ->label('Nivel de Aprobación')
                            ->options(
                                NivelAprobacion::where('Activo', true)
                                    ->orderBy('Orden')
                                    ->get()
                                    ->mapWithKeys(fn($nivel) => [
                                        $nivel->NivelAprobacionID => "{$nivel->Nombre} (S/ {$nivel->MontoMinimo} - S/ {$nivel->MontoMaximo})"
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->label('Nivel de Aprobación'),
                    ])
                    ->action(function (User $record, array $data) {
                        // Desactivar nivel anterior si existe
                        UserNivelAprobacion::where('UserID', $record->id)
                            ->where('Activo', true)
                            ->update(['Activo' => false]);

                        // Crear o actualizar el nuevo nivel
                        UserNivelAprobacion::updateOrCreate(
                            ['UserID' => $record->id],
                            [
                                'NivelAprobacionID' => $data['NivelAprobacionID'],
                                'FechaAsignacion' => now(),
                                'Activo' => true,
                            ]
                        );

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Nivel de Aprobación Asignado')
                            ->body('El nivel de aprobación se asignó correctamente')
                            ->send();
                    })
                    ->modalHeading('Asignar Nivel de Aprobación al Usuario')
                    ->modalSubmitActionLabel('Asignar'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}