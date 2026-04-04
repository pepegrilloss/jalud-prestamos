<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TasaMoraResource\Pages;
use App\Models\TasaMora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class TasaMoraResource extends Resource
{
    protected static ?string $model = TasaMora::class;

    protected static ?string $navigationGroup = 'Mantenimiento';
    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'Tasa de Mora';

    public static function getPluralModelLabel(): string
    {
        return \App\Services\DateFieldResolver::getFechaAbierta() !== null
            ? 'Tasas de Mora'
            : 'Tasas de Mora ⚠️ (Día Cerrado)';
    }

    protected static ?string $cluster = Mantenimiento::class;

    public static function canCreate(): bool
    {
        return \App\Services\DateFieldResolver::getFechaAbierta() !== null;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Services\DateFieldResolver::getFechaAbierta() !== null;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Services\DateFieldResolver::getFechaAbierta() !== null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de Tasa de Mora')
                    ->schema([
                        Forms\Components\TextInput::make('Nombre')
                            ->label('Nombre')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                                return $rule->where('SedeID', $sedeId);
                            })
                            ->maxLength(100)
                            ->placeholder('Ej: Mora 0.5%')
                            ->helperText('Nombre descriptivo de la tasa'),

                        Forms\Components\TextInput::make('Porcentaje')
                            ->label('Porcentaje (%)')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->placeholder('Ej: 0.5, 0.8, 1.0, 2.5')
                            ->helperText('Ingrese el porcentaje de mora (ej: 0.5, 0.8, 1.0)'),

                        Forms\Components\Textarea::make('Descripcion')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Descripción detallada de la tasa de mora'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('Porcentaje')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn($state) => "{$state}%")
                    ->sortable()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('Descripcion')
                    ->label('Descripción')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->Descripcion),

                Tables\Columns\BooleanColumn::make('Activo')
                    ->label('Activo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->label('Fecha Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('FechaModificacion')
                    ->label('Última Modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\TernaryFilter::make('Activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('Porcentaje', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasaMoras::route('/'),
        ];
    }
}
