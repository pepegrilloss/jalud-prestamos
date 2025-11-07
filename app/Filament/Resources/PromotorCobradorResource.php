<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotorCobradorResource\Pages;
use App\Filament\Resources\PromotorCobradorResource\RelationManagers;
use App\Models\PromotorCobrador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PromotorCobradorResource extends Resource
{
    protected static ?string $model = PromotorCobrador::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Promotores y Cobradores';

    protected static ?string $modelLabel = 'Promotor y Cobrador';
    protected static ?string $pluralModelLabel = 'Promotores y Cobradores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(40),
                        
                        Forms\Components\TextInput::make('Descripcion')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(400)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('Ciudad')
                            ->maxLength(200),
                        
                        Forms\Components\Toggle::make('Activo')
                            ->default(true)
                            ->required(),
                        
                        Forms\Components\DateTimePicker::make('FechaCreacion')
                            ->label('Fecha Creación')
                            ->default(now())
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($livewire) => $livewire instanceof Pages\EditPromotorCobrador),
                        
                        Forms\Components\DateTimePicker::make('FechaModificacion')
                            ->label('Fecha Modificación')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($livewire) => $livewire instanceof Pages\EditPromotorCobrador),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('Descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('Ciudad')
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
                    ->visible(fn () => auth()->user()->can('view_promotor::cobrador')),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->can('update_promotor::cobrador')),
                
                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Promotor/Cobrador')
                    ->modalDescription('¿Está seguro que desea desactivar este registro?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn () => auth()->user()->can('delete_promotor::cobrador'))
                    ->action(fn ($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Registro desactivado correctamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('delete_any_promotor::cobrador')),
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
            'index' => Pages\ListPromotorCobradors::route('/'),
            'create' => Pages\CreatePromotorCobrador::route('/create'),
            'view' => Pages\ViewPromotorCobrador::route('/{record}'),
            'edit' => Pages\EditPromotorCobrador::route('/{record}/edit'),
        ];
    }
}