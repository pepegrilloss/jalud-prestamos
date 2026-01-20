<?php

namespace App\Filament\Resources;

use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                    ->disabled(fn(string $operation): bool => $operation === 'edit')
                    ->unique(AperturaCierreDia::class, 'Fecha', ignoreRecord: true),

                Forms\Components\Select::make('EstadoDia')
                    ->options([
                        'ABIERTO' => 'Abierto',
                        'CERRADO' => 'Cerrado',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\DateTimePicker::make('FechaApertura')
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'ABIERTO')
                    ->default(now())
                    ->disabled(),

                Forms\Components\DateTimePicker::make('FechaCierre')
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'CERRADO'),

                Forms\Components\Select::make('UsuarioAperturaID')
                    ->relationship('usuarioApertura', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn(Forms\Get $get): bool => $get('EstadoDia') === 'ABIERTO'),

                Forms\Components\Select::make('UsuarioCierreID')
                    ->relationship('usuarioCierre', 'name')
                    ->searchable()
                    ->preload()
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
                Tables\Filters\SelectFilter::make('EstadoDia')
                    ->options([
                        'ABIERTO' => 'Abierto',
                        'CERRADO' => 'Cerrado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('Fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AperturaCierreDiaResource\Pages\GestionarAperturaCierre::route('/'),
        ];
    }

    /**
     * Solo administradores pueden gestionar apertura/cierre de día
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
