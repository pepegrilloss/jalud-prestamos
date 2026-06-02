<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TipoIgvResource\Pages;
use App\Models\TipoIgv;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TipoIgvResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TipoIgv::class;

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $cluster = Mantenimiento::class;
    protected static ?string $navigationGroup = 'Mantenimiento';
    protected static ?int $navigationGroupSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Tipo IGV';
    protected static ?string $pluralModelLabel = 'Tipos IGV';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('Porcentaje')
                    ->label('Porcentaje (%)')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->suffix('%'),
                Forms\Components\Toggle::make('Activo')
                    ->label('Activo')
                    ->default(true),
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
                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Porcentaje')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('Activo')
                    ->label('Solo activos')
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth('md')
                    ->modalHeading('Editar Tipo IGV'),
            ])
            ->bulkActions([])
            ->defaultSort('Porcentaje', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoIgvs::route('/'),
        ];
    }
}
