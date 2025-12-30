<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
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
    protected static ?string $cluster = Mantenimiento::class;

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
                            ->label('Código')
                            ->validationMessages([
                                'unique' => 'Este código ya está registrado en el sistema.',
                            ]),

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
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaModificacion')
                    ->label('Fecha Modificación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->successNotificationTitle('Tipo de Crédito eliminado correctamente'),
            ])
            ->bulkActions([])
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
