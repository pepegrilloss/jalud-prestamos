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

use App\Models\Sede;
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
        if (!parent::shouldRegisterNavigation()) { return false; }

        $user = auth()->user();
        // Ocultar para promotores/cobradores
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

    public static function canViewAny(): bool
    {
        if (!parent::canViewAny()) { return false; }

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
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        return $rule->where('SedeID', auth()->user()->getEffectiveSedeId());
                    })
                    ->helperText('P=Pronto Pago, I=Interés, M=Mora'),
                Forms\Components\TextInput::make('Nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        return $rule->where('SedeID', auth()->user()->getEffectiveSedeId());
                    }),
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

                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
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
                Tables\Actions\EditAction::make()->visible(fn($record) => static::canEdit($record)),
                Tables\Actions\Action::make('toggleActive')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->label(fn($record) => $record->Activo ? 'Eliminar' : 'Activar')
                    ->color(fn($record) => $record->Activo ? 'danger' : 'success')
                    ->icon(fn($record) => $record->Activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading(fn($record) => $record->Activo ? 'Eliminar Tipo de Exoneración' : 'Activar Tipo de Exoneración')
                    ->modalDescription(fn($record) => $record->Activo 
                        ? '¿Está seguro que desea eliminar este tipo de exoneración?' 
                        : '¿Está seguro que desea activar este tipo de exoneración?')
                    ->modalSubmitActionLabel('Confirmar')
                    ->action(fn($record) => $record->update([
                        'Activo' => !$record->Activo,
                    ]))
                    ->successNotificationTitle(fn($record) => $record->Activo ? 'Tipo de Exoneración activado correctamente' : 'Tipo de Exoneración eliminado correctamente'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoExoneraciones::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
