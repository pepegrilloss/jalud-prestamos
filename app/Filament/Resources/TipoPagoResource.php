<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TipoPagoResource\Pages;
use App\Models\TipoPago;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class TipoPagoResource extends Resource
{
    protected static ?string $model = TipoPago::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 1007;
    protected static ?string $label = 'Tipo de Pago';
    protected static ?string $pluralLabel = 'Tipos de Pago';
    protected static ?string $cluster = Mantenimiento::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre del Tipo de Pago')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                        return $rule->where('SedeID', auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID);
                    }),
                Forms\Components\Toggle::make('Activo')
                    ->label('Activo')
                    ->default(true)
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
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
                    ->modalHeading(fn($record) => $record->Activo ? 'Desactivar Tipo de Pago' : 'Activar Tipo de Pago')
                    ->modalDescription(fn($record) => $record->Activo 
                        ? '¿Está seguro que desea desactivar este tipo de pago?' 
                        : '¿Está seguro que desea activar este tipo de pago?')
                    ->modalSubmitActionLabel('Confirmar')
                    ->action(fn($record) => $record->update([
                        'Activo' => !$record->Activo,
                    ]))
                    ->successNotificationTitle(fn($record) => $record->Activo ? 'Tipo de Pago activado correctamente' : 'Tipo de Pago desactivado correctamente'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoPagos::route('/'),
            'create' => Pages\CreateTipoPago::route('/create'),
            'edit' => Pages\EditTipoPago::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
