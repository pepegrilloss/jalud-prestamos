<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Mantenimiento;
use App\Filament\Resources\TipoExoneracionResource\Pages;
use App\Models\TipoExoneracion;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoExoneracionResource extends Resource
{
    protected static ?string $model = TipoExoneracion::class;

    protected static ?string $navigationGroup = 'Mantenimiento';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?int $navigationSort = 1008;
    protected static ?string $label = 'Tipo de Exoneración';
    protected static ?string $pluralLabel = 'Tipos de Exoneración';
    protected static ?string $cluster = Mantenimiento::class;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        // Ocultar para promotores/cobradores
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Denegar acceso a promotores/cobradores
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('Codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(1)
                    ->unique(ignoreRecord: true)
                    ->helperText('P=Pronto Pago, I=Interés, M=Mora'),
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('Descripcion')
                    ->label('Descripción')
                    ->maxLength(200),
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
                Tables\Columns\TextColumn::make('Codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Descripcion')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable(),
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
                    ->modalHeading(fn($record) => $record->Activo ? 'Desactivar Tipo de Exoneración' : 'Activar Tipo de Exoneración')
                    ->modalDescription(fn($record) => $record->Activo 
                        ? '¿Está seguro que desea desactivar este tipo de exoneración?' 
                        : '¿Está seguro que desea activar este tipo de exoneración?')
                    ->modalSubmitActionLabel('Confirmar')
                    ->action(fn($record) => $record->update([
                        'Activo' => !$record->Activo,
                    ]))
                    ->successNotificationTitle(fn($record) => $record->Activo ? 'Tipo de Exoneración activado correctamente' : 'Tipo de Exoneración desactivado correctamente'),
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
            'index' => Pages\ListTipoExoneraciones::route('/'),
            'create' => Pages\CreateTipoExoneracion::route('/create'),
            'edit' => Pages\EditTipoExoneracion::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
