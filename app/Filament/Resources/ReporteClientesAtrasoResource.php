<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteClientesAtrasoResource\Pages;
use App\Models\CreditoReporteAtraso;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReporteClientesAtrasoResource extends Resource
{
    protected static ?string $model = CreditoReporteAtraso::class;

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

                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Monto + Interés')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('dias_atraso_calc')
                    ->label('Días de Atraso')
                    // OPTIMIZACIÓN N+1: lee ultimo_pago de la subquery en modifyQueryUsing
                    // en vez de ejecutar pagos()->max('FechaPago') por cada fila.
                    ->getStateUsing(function ($record) {
                        $fechaReferencia = $record->ultimo_pago ?? $record->FechaGeneracion;
                        if (!$fechaReferencia) return 0;

                        return \App\Services\DiasHabilesCalculator::contarDiasHabiles(
                            \Carbon\Carbon::parse($fechaReferencia)->addDay(),
                            now()
                        );
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
            ->filters([
                Tables\Filters\SelectFilter::make('cliente')
                    ->label('Cliente')
                    ->relationship('proposicion.cliente', 'NombresApellidos')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('fecha')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'] ?? null,
                                fn(Builder $q, $date) => $q->whereDate('FechaVencimiento', '>=', $date)
                            )
                            ->when(
                                $data['fecha_hasta'] ?? null,
                                fn(Builder $q, $date) => $q->whereDate('FechaVencimiento', '<=', $date)
                            );
                    }),

            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->select('Credito.*')
                    ->selectRaw("DATEDIFF(NOW(), COALESCE((SELECT MAX(FechaPago) FROM pago WHERE pago.CreditoID = Credito.CreditoID AND pago.Activo = 1), FechaGeneracion)) as dias_atraso_calc")
                    // OPTIMIZACIÓN N+1: Precalcular ultimo_pago en una subquery por página
                    // para evitar pagos()->max('FechaPago') por cada fila.
                    ->addSelect([
                        'ultimo_pago' => \App\Models\Pago::select('pago.FechaPago')
                            ->whereColumn('pago.CreditoID', '=', 'Credito.CreditoID')
                            ->where('pago.Activo', 1)
                            ->orderByDesc('pago.FechaPago')
                            ->limit(1),
                    ])
                    ->where('Activo', 1)
                    ->whereHas('proposicion', function ($q) {
                        $q->where('SaldoPendiente', '>', 0);
                    })
                    ->havingRaw('dias_atraso_calc >= 1')
                    ->with(['proposicion.cliente', 'proposicion.zona', 'proposicion.tipoCredito']);
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('dias_atraso_calc', 'desc')
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
