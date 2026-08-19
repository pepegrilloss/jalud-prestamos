<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogResource\Pages;
use App\Models\Log;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class LogResource extends Resource
{
    protected static ?string $model = Log::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Auditoría';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int $navigationGroupSort = 10;
    protected static ?string $modelLabel = 'Log';
    protected static ?string $pluralModelLabel = 'Logs';

    public static function canViewAny(): bool
    {
        return auth()->user()?->puedeGestionarUsuariosYRoles() || parent::canViewAny();
    }

    public static function canView($record): bool
    {
        return auth()->user()?->puedeGestionarUsuariosYRoles() || parent::canView($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->puedeAccederAGerencia()) {
            return $query->withoutGlobalScope('sede');
        }

        return $query;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->select([
                'logs.id',
                'logs.user_id',
                'logs.accion',
                'logs.modelo',
                'logs.modelo_id',
                'logs.ip_address',
                'logs.machine_name',
                'logs.created_at',
                'logs.SedeID',
            ]))
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
                    ->color(fn(string $state) => match ($state) {
                        'CREAR' => 'success',
                        'ACTUALIZAR' => 'info',
                        'ELIMINAR' => 'danger',
                        'LOGIN' => 'success',
                        'LOGOUT' => 'warning',
                        'LOGIN_FALLIDO' => 'danger',
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
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn() => auth()->user()?->puedeAccederAGerencia() ?? false),
                Tables\Filters\SelectFilter::make('accion')
                    ->label('Acción')
                    ->options([
                        'CREAR' => 'Crear',
                        'ACTUALIZAR' => 'Actualizar',
                        'ELIMINAR' => 'Eliminar',
                        'LOGIN' => 'Login',
                        'LOGOUT' => 'Logout',
                        'LOGIN_FALLIDO' => 'Login fallido',
                    ]),

                Tables\Filters\SelectFilter::make('modelo')
                    ->label('Modelo')
                    ->options(fn() => \App\Models\Log::distinct()->pluck('modelo', 'modelo')->toArray()),
            ])
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
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
