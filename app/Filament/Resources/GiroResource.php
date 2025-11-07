<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiroResource\Pages;
use App\Filament\Resources\GiroResource\RelationManagers;
use App\Models\Giro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GiroResource extends Resource
{
    protected static ?string $model = Giro::class;

    protected static ?string $navigationGroup = 'Mantenimiento';
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Codigo')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('Descripcion')
                    ->required()
                    ->maxLength(400),
                Forms\Components\DateTimePicker::make('FechaCreacion')
                    ->required(),
                Forms\Components\DateTimePicker::make('FechaModificacion')
                    ->visible(fn($livewire) => $livewire instanceof Pages\EditGiro)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Codigo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Descripcion')
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
                    ->visible(fn() => auth()->user()->can('view_giro')),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->can('update_giro')),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Giro')
                    ->modalDescription('¿Está seguro que desea desactivar este giro?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => auth()->user()->can('delete_giro'))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Giro desactivado correctamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_any_giro')),
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
            'index' => Pages\ListGiros::route('/'),
            'create' => Pages\CreateGiro::route('/create'),
            'view' => Pages\ViewGiro::route('/{record}'),
            'edit' => Pages\EditGiro::route('/{record}/edit'),
        ];
    }
}
