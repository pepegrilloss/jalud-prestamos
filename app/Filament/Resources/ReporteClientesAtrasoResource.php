<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteClientesAtrasoResource\Pages;
use App\Models\Credito;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReporteClientesAtrasoResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Clientes con Atraso';
    protected static ?string $pluralLabel = 'Clientes con Días de Atraso';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.zona.Nombre')
                    ->label('Zona')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('dias_atraso')
                    ->label('Días de Atraso')
                    ->getStateUsing(function ($record) {
                        $ultimoPago = $record->pagos()
                            ->where('Activo', 1)
                            ->max('FechaPago');

                        $fechaReferencia = $ultimoPago ?? $record->FechaGeneracion;

                        if (!$fechaReferencia) return 0;

                        return max(0, (int) now()->startOfDay()->diffInDays($fechaReferencia));
                    })
                    ->sortable()
                    ->color('danger')
                    ->badge()
                    ->icon('heroicon-m-clock'),

                Tables\Columns\TextColumn::make('FechaVencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $ayer = now()->subDay()->endOfDay();

                $query->where('Activo', 1)
                    ->whereHas('proposicion', function ($q) {
                        $q->where('SaldoPendiente', '>', 0);
                    })
                    ->whereRaw("(
                        SELECT COALESCE(MAX(FechaPago), FechaGeneracion)
                        FROM pago
                        WHERE pago.CreditoID = Credito.CreditoID AND pago.Activo = 1
                    ) <= ?", [$ayer])
                    ->with(['proposicion.cliente', 'proposicion.zona', 'proposicion.tipoCredito'])
                    ->orderByRaw("(
                        SELECT COALESCE(MAX(FechaPago), FechaGeneracion)
                        FROM pago
                        WHERE pago.CreditoID = Credito.CreditoID AND pago.Activo = 1
                    ) ASC");
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('FechaVencimiento', 'asc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_reporte::clientes::atraso');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteClientesAtraso::route('/'),
        ];
    }
}
