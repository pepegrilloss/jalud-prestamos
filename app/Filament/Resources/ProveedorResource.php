<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use App\Filament\Resources\ProveedorResource\Pages;
use App\Models\Proveedor;
use App\Models\AperturaCierreDia;
use App\Models\Sede;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProveedorResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Proveedor::class;

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $navigationGroup = 'Mantenimiento';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 1009;
    protected static ?string $cluster = Mantenimiento::class;

    protected static ?string $label = 'Proveedor';
    protected static ?string $pluralLabel = 'Proveedores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        return $rule->where('SedeID', auth()->user()->getEffectiveSedeId());
                    })
                    ->validationMessages([
                        'unique' => 'Este código ya está registrado en el sistema.',
                    ]),
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre / Razón Social')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('RUC')
                    ->label('RUC')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('Direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('Telefono')
                    ->label('Teléfono')
                    ->maxLength(20)
                    ->tel(),
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
                Tables\Columns\TextColumn::make('Codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre / Razón Social')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('RUC')
                    ->label('RUC')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Direccion')
                    ->label('Dirección')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Telefono')
                    ->label('Teléfono')
                    ->searchable(),
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
                    ->visible(fn() => auth()->user()->esAdmin()),
                Tables\Filters\TernaryFilter::make('Activo')
                    ->label('Estado')
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
                    ->modalHeading('Eliminar Proveedor')
                    ->modalDescription('¿Está seguro que desea eliminar este proveedor?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->action(fn($record) => $record->update([
                        'Activo' => false,
                        'FechaModificacion' => now(),
                    ]))
                    ->successNotificationTitle('Proveedor eliminado correctamente'),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProveedores::route('/'),
        ];
    }
}
