<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HistorialExoneracionResource\Pages;
use App\Models\HistorialExoneracion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistorialExoneracionResource extends Resource
{
    protected static ?string $model = HistorialExoneracion::class;

    protected static ?string $navigationGroup = 'Exoneraciones';
    protected static ?int $navigationGroupSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?int $navigationSort = 11;
    protected static ?string $label = 'Historial de Exoneración';
    protected static ?string $pluralLabel = 'Historial de Exoneraciones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('cliente_nombre')
                ->label('Cliente')
                ->disabled(),
            Forms\Components\TextInput::make('credito_codigo')
                ->label('Crédito')
                ->disabled(),
            Forms\Components\Select::make('TipoExoneracion')
                ->label('Tipo')
                ->options([
                    'P' => 'Pronto Pago',
                    'I' => 'Interés',
                    'M' => 'Mora',
                ])
                ->disabled(),
            Forms\Components\TextInput::make('MontoExonerado')
                ->label('Monto')
                ->disabled(),
            Forms\Components\TextInput::make('UsuarioAprobador')
                ->label('Usuario Aprobador')
                ->disabled(),
            Forms\Components\Textarea::make('Comentario')
                ->label('Comentario')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credito.proposicion.CodigoCredito')
                    ->label('Crédito')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('TipoExoneracion')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match($state) {
                        'P' => 'Pronto Pago',
                        'I' => 'Interés',
                        'M' => 'Mora',
                        default => $state
                    })
                    ->colors([
                        'success' => 'P',
                        'info' => 'I',
                        'warning' => 'M',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('MontoExonerado')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('UsuarioAprobador')
                    ->label('Aprobador')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaExoneracion')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoExoneracion')
                    ->label('Tipo')
                    ->options([
                        'P' => 'Pronto Pago',
                        'I' => 'Interés',
                        'M' => 'Mora',
                    ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['cliente', 'credito.proposicion'])
                    ->orderBy('FechaExoneracion', 'desc');
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistorialExoneraciones::route('/'),
            'view' => Pages\ViewHistorialExoneracion::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}
