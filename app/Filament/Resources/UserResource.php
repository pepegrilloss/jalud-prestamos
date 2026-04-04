<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Sede;
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

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'username'];
    }

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
                            ->preload()
                            ->searchable()
                            ->live()
                            ->required()
                            ->label('Roles'),

                        Forms\Components\Select::make('PromotorCobradorID')
                            ->relationship('promotorCobrador', 'Descripcion')
                            ->label('Promotor Cobrador')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(function (Forms\Get $get) {
                                $roleId = $get('roles');
                                if (!$roleId) return false;
                                
                                // Asegurar que tengamos un solo ID si Filament devuelve un array
                                $roleId = is_array($roleId) ? ($roleId[0] ?? null) : $roleId;
                                
                                if (!$roleId) return false;

                                $roleModel = \BezhanSalleh\FilamentShield\Support\Utils::getRoleModel();
                                $role = $roleModel::find($roleId);
                                
                                // find() puede devolver una colección si recibe un array por error, 
                                // validamos que sea un objeto individual
                                return $role instanceof \Illuminate\Database\Eloquent\Model && $role->name === 'promotor_cobrador';
                            })
                            ->required()
                            ->helperText('Asignar un promotor cobrador a este usuario'),

                        Forms\Components\Select::make('SedeID')
                            ->label('Sede')
                            ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->helperText('Sede a la que pertenece este usuario'),
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

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->badge()
                    ->color('info')
                    ->placeholder('Sin sede')
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
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn() => auth()->user()->esAdmin()),
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
                        // Eliminar nivel anterior si existe
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