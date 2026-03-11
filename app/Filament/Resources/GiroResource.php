<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\GiroResource\Pages;
use App\Filament\Resources\GiroResource\RelationManagers;
use App\Models\Giro;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Sede;
class GiroResource extends Resource
{
    protected static ?string $model = Giro::class;

    protected static ?string $navigationGroup = 'Mantenimiento';
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?int $navigationSort = 1002;
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Codigo')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Este código ya está registrado en el sistema.',
                    ]),
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
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Codigo')
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
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto()),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Giro')
                    ->modalDescription('¿Está seguro que desea desactivar este giro?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto())
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Giro desactivado correctamente'),
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
            'index' => Pages\ListGiros::route('/'),
            'create' => Pages\CreateGiro::route('/create'),
            'view' => Pages\ViewGiro::route('/{record}'),
            'edit' => Pages\EditGiro::route('/{record}/edit'),
        ];
    }
}
