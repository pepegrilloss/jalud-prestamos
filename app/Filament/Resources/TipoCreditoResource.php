<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoCreditoResource\Pages;
use App\Models\TipoCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoCreditoResource extends Resource
{
    protected static ?string $model = TipoCredito::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 1006;

    protected static ?string $navigationLabel = 'Tipos de Crédito';

    protected static ?string $modelLabel = 'Tipo de Crédito';

    protected static ?string $pluralModelLabel = 'Tipos de Crédito';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Codigo')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->label('Código'),

                        Forms\Components\TextInput::make('Descripcion')
                            ->required()
                            ->maxLength(100)
                            ->label('Descripción')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('Activo')
                            ->hidden()
                            ->default(true),

                        Forms\Components\DateTimePicker::make('FechaCreacion')
                            ->label('Fecha Creación')
                            ->hidden()
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('FechaModificacion')
                            ->label('Fecha Modificación')
                            ->hidden()
                            ->default(now()),
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
                    ->visible(fn() => auth()->user()->can('view_tipo_credito')),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->can('update_tipo_credito')),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Tipo de Crédito')
                    ->modalDescription('¿Está seguro que desea desactivar este tipo de crédito?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => auth()->user()->can('delete_tipo_credito'))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Tipo de Crédito desactivado correctamente'),
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
            'index' => Pages\ListTiposCredito::route('/'),
            'create' => Pages\CreateTipoCredito::route('/create'),
            'view' => Pages\ViewTipoCredito::route('/{record}'),
            'edit' => Pages\EditTipoCredito::route('/{record}/edit'),
        ];
    }
}
