<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubGiroResource\Pages;
use App\Filament\Resources\SubGiroResource\RelationManagers;
use App\Models\SubGiro;
use App\Models\Giro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubGiroResource extends Resource
{
    protected static ?string $model = SubGiro::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('GiroID')
                    ->label('Giro')
                    ->options(Giro::where('Activo', true)->pluck('Descripcion', 'GiroID'))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('Descripcion')
                    ->required()
                    ->maxLength(400),
                Forms\Components\DateTimePicker::make('FechaCreacion')
                    ->required(),
                Forms\Components\DateTimePicker::make('FechaModificacion')
                    ->visible(fn($livewire) => $livewire instanceof Pages\EditSubGiro)
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Giro.Descripcion')
                    ->label('Giro')
                    ->sortable()
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
                    ->visible(fn() => auth()->user()->can('view_sub::giro')),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->can('update_sub::giro')),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Sub Giro')
                    ->modalDescription('¿Está seguro que desea desactivar este sub giro?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => auth()->user()->can('delete_sub::giro'))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Sub Giro desactivado correctamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_any_sub::giro')),
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
            'index' => Pages\ListSubGiros::route('/'),
            'create' => Pages\CreateSubGiro::route('/create'),
            'view' => Pages\ViewSubGiro::route('/{record}'),
            'edit' => Pages\EditSubGiro::route('/{record}/edit'),
        ];
    }
}
