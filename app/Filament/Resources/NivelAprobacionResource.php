<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\NivelAprobacionResource\Pages;
use App\Models\NivelAprobacion;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class NivelAprobacionResource extends Resource
{
    protected static ?string $model = NivelAprobacion::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 1008;

    protected static ?string $navigationLabel = 'Niveles de Aprobación';

    protected static ?string $modelLabel = 'Nivel de Aprobación';

    protected static ?string $pluralModelLabel = 'Niveles de Aprobación';
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Nombre')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                                return $rule->where('SedeID', $sedeId);
                            })
                            ->label('Nombre')
                            ->placeholder('Ej: Nivel 1, Gerente, etc.'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('MontoMinimo')
                                    ->required()
                                    ->numeric()
                                    ->label('Monto Mínimo')
                                    ->placeholder('0.00'),

                                Forms\Components\TextInput::make('MontoMaximo')
                                    ->required()
                                    ->numeric()
                                    ->label('Monto Máximo')
                                    ->placeholder('0.00')
                                    ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                        if ($state && $get('MontoMinimo')) {
                                            if ((float) $state < (float) $get('MontoMinimo')) {
                                                $set('MontoMaximo', $get('MontoMinimo'));
                                            }
                                        }
                                    }),
                            ]),

                        Forms\Components\TextInput::make('Orden')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->label('Orden de Jerarquía')
                            ->helperText('1 = Mayor jerarquía, números mayores = menor jerarquía')
                            ->placeholder('Ej: 1')
                            ->step(1)
                            ->minValue(1),

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
                Tables\Columns\TextColumn::make('Orden')
                    ->label('Jerarquía')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoMinimo')
                    ->label('Monto Mínimo')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoMaximo')
                    ->label('Monto Máximo')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rango')
                    ->label('Rango de Aprobación')
                    ->state(function (NivelAprobacion $record) {
                        return "S/ {$record->MontoMinimo} - S/ {$record->MontoMaximo}";
                    }),

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

                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Nivel de Aprobación')
                    ->modalDescription('¿Está seguro que desea desactivar este nivel de aprobación?')
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto())
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Nivel de Aprobación desactivado correctamente'),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('Orden')
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
            'index' => Pages\ListNivelAprobacions::route('/'),
            'create' => Pages\CreateNivelAprobacion::route('/create'),
            'view' => Pages\ViewNivelAprobacion::route('/{record}'),
            'edit' => Pages\EditNivelAprobacion::route('/{record}/edit'),
        ];
    }
}
