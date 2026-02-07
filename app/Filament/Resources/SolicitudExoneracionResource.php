<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitudExoneracionResource\Pages;
use App\Models\SolicitudExoneracion;
use App\Models\Credito;
use App\Models\AperturaCierreDia;
use App\Models\Zona;
use App\Models\TipoCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SolicitudExoneracionResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationGroup = 'Exoneraciones';
    protected static ?int $navigationGroupSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Descuentos y Exoneraciones';
    protected static ?string $pluralLabel = 'Descuentos y Exoneraciones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Card::make()->schema([])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposicion.zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Monto + Interés')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo Pendiente')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('Fecha Generación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('ZonaID', $data['value']))
                        );
                    })
                    ->native(false),
                Tables\Filters\SelectFilter::make('tipoCredito')
                    ->label('Tipo de Crédito')
                    ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('TipoCreditoID', $data['value']))
                        );
                    })
                    ->native(false),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with(['proposicion', 'tipoPago'])
                    ->where('Activo', 1)
                    ->whereHas('proposicion', function (Builder $q) {
                        $q->where('SaldoPendiente', '>', 0)
                            ->where('FueRefinanciada', 0);
                    });
            })
            ->actions([
                Tables\Actions\Action::make('crear_exoneracion')
                    ->label('Descuentos y Exoneraciones')
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->url(fn($record) => SolicitudExoneracionResource::getUrl('create') . '?CreditoID=' . $record->CreditoID),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('FechaGeneracion', 'desc')
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
            'index' => Pages\ListSolicitudExoneraciones::route('/'),
            'create' => Pages\CreateSolicitudExoneracion::route('/create'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}

