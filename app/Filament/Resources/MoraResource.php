<?php
/*
namespace App\Filament\Resources;
use App\Filament\Resources\MoraResource\Pages;
use App\Models\Mora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class MoraResource extends Resource
{
    protected static ?string $model = Mora::class;
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?int $navigationSort = 5;
    protected static ?string $label = 'Mora';
    protected static ?string $pluralLabel = 'Moras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de Mora')
                    ->schema([
                        Forms\Components\Select::make('CreditoID')
                            ->label('Crédito')
                            ->disabled()
                            ->getStateUsing(fn($record) => $record?->CreditoID),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('FechaMora')
                                    ->label('Fecha de Mora')
                                    ->disabled()
                                    ->formatStateUsing(fn($state) => $state?->format('d/m/Y')),

                                Forms\Components\TextInput::make('SaldoPendiente')
                                    ->label('Saldo Pendiente')
                                    ->disabled()
                                    ->prefix('S/'),

                                Forms\Components\TextInput::make('PorcentajeMora')
                                    ->label('Porcentaje Aplicado')
                                    ->disabled()
                                    ->suffix('%'),

                                Forms\Components\TextInput::make('MontoMora')
                                    ->label('Monto de Mora (Diaria)')
                                    ->disabled()
                                    ->prefix('S/'),

                                Forms\Components\TextInput::make('MoraAcumulada')
                                    ->label('Mora Acumulada')
                                    ->disabled()
                                    ->prefix('S/'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('credito.proposicion.cliente.DNI')
                    ->label('DNI Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('credito.proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('CreditoID')
                    ->label('Crédito ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaMora')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('SaldoPendiente')
                    ->label('Saldo Pendiente')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('PorcentajeMora')
                    ->label('% Mora')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoMora')
                    ->label('Mora Diaria')
                    ->money('PEN')
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MoraAcumulada')
                    ->label('Mora Acumulada')
                    ->money('PEN')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('CreditoID')
                    ->label('Crédito')
                    ->relationship('credito', 'CreditoID')
                    ->searchable(),

                Tables\Filters\Filter::make('FechaMora')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $q) => $q->whereDate('FechaMora', '>=', $data['desde'])
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $q) => $q->whereDate('FechaMora', '<=', $data['hasta'])
                            );
                    }),
            ])
            ->defaultSort('FechaMora', 'desc')
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMoras::route('/'),
        ];
    }

    // Solo lectura - no permitir crear/editar/eliminar manualmente
    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canEdit($record)) { return false; }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canDelete($record)) { return false; }

        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
*/