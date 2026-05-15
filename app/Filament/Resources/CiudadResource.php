<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\CiudadResource\Pages;
use App\Filament\Resources\CiudadResource\RelationManagers;
use App\Models\Ciudad;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


use App\Models\Sede;
class CiudadResource extends Resource
{
    protected static ?string $model = Ciudad::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 1001;

    protected static ?string $navigationLabel = 'Ciudades';

    protected static ?string $modelLabel = 'Ciudad';

    protected static ?string $pluralModelLabel = 'Ciudades';

    protected static ?string $cluster = Mantenimiento::class;

    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Nombre')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('SedeID', auth()->user()->getEffectiveSedeId());
                            })
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
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('CiudadID')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
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
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\TernaryFilter::make('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\EditAction::make()->visible(fn($record) => static::canEdit($record)),
                
                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Ciudad')
                    ->modalDescription('¿Está seguro que desea eliminar esta ciudad?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn($record) => static::canDelete($record))
                    ->action(fn ($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Ciudad eliminada correctamente'),
            ])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

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
            'index' => Pages\ListCiudads::route('/'),
        ];
    }
}