<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\SubGiroResource\Pages;
use App\Models\SubGiro;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Sede;
class SubGiroResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = SubGiro::class;

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

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
                    ->maxLength(400)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, Forms\Get $get) {
                        return $rule->where('SedeID', auth()->user()->getEffectiveSedeId())
                                    ->where('GiroID', $get('GiroID'));
                    }),
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

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->searchable()
                    ->sortable(),
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

                Tables\Actions\EditAction::make()->visible(fn($record) => static::canEdit($record)),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Sub Giro')
                    ->modalDescription('¿Está seguro que desea eliminar este sub giro?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn($record) => static::canDelete($record))
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now()
                    ]))
                    ->successNotificationTitle('Sub Giro eliminado correctamente'),
            ])
            ->bulkActions([])
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
            'index' => Pages\ListSubGiros::route('/'),
        ];
    }
}
