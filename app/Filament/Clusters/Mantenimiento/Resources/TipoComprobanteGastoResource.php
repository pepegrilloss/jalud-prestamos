<?php

namespace App\Filament\Clusters\Mantenimiento\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource\Pages;
use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource\RelationManagers;
use App\Models\TipoComprobanteGasto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\AperturaCierreDia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TipoComprobanteGastoResource extends Resource
{
    protected static ?string $model = TipoComprobanteGasto::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $label = 'Tipo de Comprobante (Gastos)';
    protected static ?string $pluralLabel = 'Tipos de Comprobante (Gastos)';

    protected static ?string $cluster = Mantenimiento::class;
    protected static ?int $navigationSort = 1008;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre del Tipo de Comprobante')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                        return $rule->where('SedeID', $sedeId);
                    }),
                Forms\Components\Toggle::make('Activo')
                    ->label('Activo')
                    ->default(true)
                    ->hidden(fn(string $operation): bool => $operation === 'create'),
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
                Tables\Filters\TernaryFilter::make('Activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),
                Tables\Actions\Action::make('toggleActive')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->label(fn($record) => $record->Activo ? 'Desactivar' : 'Activar')
                    ->color(fn($record) => $record->Activo ? 'danger' : 'success')
                    ->icon(fn($record) => $record->Activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading(fn($record) => $record->Activo ? 'Desactivar Tipo' : 'Activar Tipo')
                    ->modalSubmitActionLabel('Confirmar')
                    ->action(fn($record) => $record->update([
                        'Activo' => !$record->Activo,
                    ]))
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record) && \App\Models\AperturaCierreDia::estaAbierto();
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
            'index' => Pages\ListTipoComprobanteGastos::route('/'),
            'create' => Pages\CreateTipoComprobanteGasto::route('/create'),
            'edit' => Pages\EditTipoComprobanteGasto::route('/{record}/edit'),
        ];
    }
}
