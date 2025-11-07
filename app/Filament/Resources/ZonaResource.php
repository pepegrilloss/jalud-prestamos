<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZonaResource\Pages;
use App\Filament\Resources\ZonaResource\RelationManagers;
use App\Models\Zona;
use App\Models\Ciudad;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ZonaResource extends Resource
{
    protected static ?string $model = Zona::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('CiudadID')
                    ->label('Ciudad')
                    ->options(Ciudad::where('Activo', true)->pluck('Nombre', 'CiudadID'))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('Nombre')
                    ->required()
                    ->maxLength(200),
                Forms\Components\DateTimePicker::make('FechaCreacion')
                    ->required(),
                Forms\Components\DateTimePicker::make('FechaModificacion')
                    ->visible(fn($livewire) => $livewire instanceof Pages\EditZona)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Ciudad.Nombre')
                    ->label('Ciudad')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('Nombre')
                    ->searchable(),
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
                    ->visible(fn() => auth()->user()->can('view_zona')),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->can('update_zona')),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Zona')
                    ->modalDescription('¿Está seguro que desea desactivar esta zona?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => auth()->user()->can('delete_zona'))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Zona desactivada correctamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_any_zona')),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
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
            'index' => Pages\ListZonas::route('/'),
            'create' => Pages\CreateZona::route('/create'),
            'view' => Pages\ViewZona::route('/{record}'),
            'edit' => Pages\EditZona::route('/{record}/edit'),
        ];
    }
}
