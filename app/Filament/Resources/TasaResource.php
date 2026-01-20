<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TasaResource\Pages;
use App\Filament\Resources\TasaResource\RelationManagers;
use App\Models\Tasa;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TasaResource extends Resource
{
    protected static ?string $model = Tasa::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 1005;
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Nombre')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('Valor')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('Dias')
                    ->required()
                    ->numeric()
                    ->integer(),
                Forms\Components\TextInput::make('Cuotas')
                    ->required()
                    ->numeric()
                    ->integer(),
                Forms\Components\Toggle::make('Activo')
                    ->hidden()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Valor')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Dias')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Cuotas')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaModificacion')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn() => auth()->user()->can('view_tasa')),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto() && auth()->user()->can('update_tasa')),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Tasa')
                    ->modalDescription('¿Está seguro que desea desactivar esta tasa?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => auth()->user()->can('delete_tasa'))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                    ]))
                    ->successNotificationTitle('Tasa desactivada correctamente'),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasas::route('/'),
            'create' => Pages\CreateTasa::route('/create'),
            'view' => Pages\ViewTasa::route('/{record}'),
            'edit' => Pages\EditTasa::route('/{record}/edit'),
        ];
    }
}
