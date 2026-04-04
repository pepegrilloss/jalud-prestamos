<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\MotivoResource\Pages;
use App\Models\Motivo;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class MotivoResource extends Resource
{
    protected static ?string $model = Motivo::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 1016;
    protected static ?string $label = 'Motivo de Gasto';
    protected static ?string $pluralLabel = 'Motivos de Gasto';
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre del Motivo')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                        return $rule->where('SedeID', $sedeId);
                    }),
                Forms\Components\Toggle::make('Activo')
                    ->label('Activo')
                    ->default(true)
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('Activo')
                    ->label('Estado')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->label('Fecha Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaModificacion')
                    ->label('Fecha Modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => AperturaCierreDia::estaAbierto()),
                ]),
            ])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate(...func_get_args()) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit(...func_get_args()) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete(...func_get_args()) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotivos::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
