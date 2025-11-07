<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CiudadResource\Pages;
use App\Filament\Resources\CiudadResource\RelationManagers;
use App\Models\Ciudad;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CiudadResource extends Resource
{
    protected static ?string $model = Ciudad::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Ciudades';

    protected static ?string $modelLabel = 'Ciudad';
    protected static ?string $pluralModelLabel = 'Ciudades Te Amo Ariana';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Nombre')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        
                        Forms\Components\Toggle::make('Activo')
                            ->default(true)
                            ->required(),
                        
                        Forms\Components\DateTimePicker::make('FechaCreacion')
                            ->label('Fecha Creación')
                            ->default(now())
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($livewire) => $livewire instanceof Pages\EditCiudad),
                        
                        Forms\Components\DateTimePicker::make('FechaModificacion')
                            ->label('Fecha Modificación')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($livewire) => $livewire instanceof Pages\EditCiudad),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('CiudadID')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('Nombre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->label('Fecha Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('FechaModificacion')
                    ->label('Fecha Modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn () => auth()->user()->can('view_ciudad')),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->can('update_ciudad')),
                
                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Ciudad')
                    ->modalDescription('¿Está seguro que desea desactivar esta ciudad?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn () => auth()->user()->can('delete_ciudad'))
                    ->action(fn ($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Ciudad desactivada correctamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('delete_any_ciudad')),
                ]),
            ])
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
            'index' => Pages\ListCiudads::route('/'),
            'create' => Pages\CreateCiudad::route('/create'),
            'view' => Pages\ViewCiudad::route('/{record}'),
            'edit' => Pages\EditCiudad::route('/{record}/edit'),
        ];
    }
}