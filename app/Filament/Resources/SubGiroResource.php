<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\SubGiroResource\Pages;
use App\Filament\Resources\SubGiroResource\RelationManagers;
use App\Models\SubGiro;
use App\Models\Giro;
use App\Models\AperturaCierreDia;
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
    protected static ?int $navigationSort = 1003;
    protected static ?string $cluster = Mantenimiento::class;

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
                Forms\Components\Toggle::make('Activo')
                    ->hidden()
                    ->default(true),
                Forms\Components\DateTimePicker::make('FechaCreacion')
                    ->hidden()
                    ->default(now()),
                Forms\Components\DateTimePicker::make('FechaModificacion')
                    ->hidden()
                    ->default(now())
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
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto()),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Sub Giro')
                    ->modalDescription('¿Está seguro que desea desactivar este sub giro?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto())
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Sub Giro desactivado correctamente'),
            ])
            ->bulkActions([])
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
