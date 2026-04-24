<?php

namespace App\Filament\Resources;

use App\Models\CalendarioNoMoroso;
use App\Models\AperturaCierreDia;
use App\Models\Sede;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CalendarioNoMorosoResource extends Resource
{
    protected static ?string $model = CalendarioNoMoroso::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Calendario No Moroso';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Fecha No Morosa';
    protected static ?string $pluralLabel = 'Calendario No Moroso';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('Fecha')
                    ->required()
                    ->native(false)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                        return $rule->where('SedeID', $sedeId);
                    }),

                Forms\Components\TextInput::make('Descripcion')
                    ->label('Descripción')
                    ->maxLength(255)
                    ->placeholder('Ej: Domingo de trabajo, Feriado laborable...'),

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
                Tables\Columns\TextColumn::make('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('Descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('Activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->label('Fecha Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn() => auth()->user()->esAdmin()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => static::canEdit($record)),
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
            ->paginationPageOptions([10, 25, 50])
            ->defaultSort('Fecha', 'desc');
    }

    public static function canCreate(): bool
    {
        return parent::canCreate(...func_get_args()) && AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit(...func_get_args()) && AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete(...func_get_args()) && AperturaCierreDia::estaAbierto();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CalendarioNoMorosoResource\Pages\ListCalendarioNoMorosos::route('/'),
        ];
    }
}
