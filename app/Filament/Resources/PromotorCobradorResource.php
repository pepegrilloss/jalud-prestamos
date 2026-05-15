<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\PromotorCobradorResource\Pages;
use App\Filament\Resources\PromotorCobradorResource\RelationManagers;
use App\Models\PromotorCobrador;
use App\Models\Ciudad;
use App\Models\Zona;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Sede;
class PromotorCobradorResource extends Resource
{
    protected static ?string $model = PromotorCobrador::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1004;

    protected static ?string $navigationLabel = 'Promotores y Cobradores';

    protected static ?string $modelLabel = 'Promotor y Cobrador';
    protected static ?string $pluralModelLabel = 'Promotores y Cobradores';
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('Codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(40)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('SedeID', auth()->user()->getEffectiveSedeId());
                            })
                            ->validationMessages([
                                'unique' => 'Este código ya está registrado en el sistema.',
                            ]),

                        Forms\Components\TextInput::make('Descripcion')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(400)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('CiudadID')
                            ->label('Ciudad')
                            ->options(Ciudad::where('Activo', true)->pluck('Nombre', 'CiudadID'))
                            ->required(),

                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                            ->required(),

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
                Tables\Columns\TextColumn::make('Codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('Descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('ciudad.Nombre')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaCreacion')
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
                    ->modalHeading('Eliminar Promotor/Cobrador')
                    ->modalDescription('¿Está seguro que desea eliminar este registro?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn($record) => static::canDelete($record))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Registro eliminado correctamente'),
            ])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record);
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record);
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
            'index' => Pages\ListPromotorCobradors::route('/'),
        ];
    }
}