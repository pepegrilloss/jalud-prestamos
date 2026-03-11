<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TipoComprobanteResource\Pages;
use App\Models\TipoComprobante;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class TipoComprobanteResource extends Resource
{
    protected static ?string $model = TipoComprobante::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1015;
    protected static ?string $label = 'Tipo de Comprobante (Compras)';
    protected static ?string $pluralLabel = 'Tipos de Comprobante (Compras)';
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre del Tipo de Comprobante')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
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
            'index' => Pages\ListTipoComprobantes::route('/'),
            'create' => Pages\CreateTipoComprobante::route('/create'),
            'edit' => Pages\EditTipoComprobante::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
