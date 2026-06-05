<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteCreditosVencidosResource\Pages;
use App\Models\CreditoReporteVencido;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class ReporteCreditosVencidosResource extends Resource
{
    protected static ?string $model = CreditoReporteVencido::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Créditos Vencidos';
    protected static ?string $pluralLabel = 'Créditos Vencidos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // This is a read-only resource
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.cliente.DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('TIPO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.zona.Nombre')
                    ->label('Zona')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('totalPagado')
                    ->label('Pagado')
                    ->money('PEN')
                    // OPTIMIZACIÓN N+1: lee el atributo precalculado por la subquery en modifyQueryUsing.
                    ->getStateUsing(fn ($record) => (float) ($record->total_pagado ?? 0))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("(SELECT COALESCE(SUM(p.MontoPagado), 0) FROM pago p JOIN cuota c ON p.CuotaID = c.CuotaID WHERE c.CreditoID = Credito.CreditoID AND p.Activo = 1) {$direction}")),

                Tables\Columns\TextColumn::make('saldoPendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    // OPTIMIZACIÓN N+1: usa total_pagado (subquery) y proposicion (eager loaded).
                    ->getStateUsing(function ($record) {
                        $total = (float) ($record->proposicion->MontoTotalPagar ?? 0);
                        $pagado = (float) ($record->total_pagado ?? 0);
                        return $total - $pagado;
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("(SELECT pc.SaldoPendiente FROM ProposicionCredito pc WHERE pc.ProposicionCreditoID = Credito.ProposicionCreditoID) {$direction}")),

                Tables\Columns\TextColumn::make('FechaVencimiento')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('cliente')
                    ->label('Cliente')
                    ->relationship('proposicion.cliente', 'NombresApellidos')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('tipoCredito')
                    ->label('Tipo Crédito')
                    ->relationship('proposicion.tipoCredito', 'Descripcion')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('FechaVencimiento')
                    ->label('Fecha de Vencimiento')
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
                                function (Builder $q, $date) {
                                    return $q->whereDate('FechaVencimiento', '>=', $date);
                                }
                            )
                            ->when(
                                $data['fecha_hasta'] ?? null,
                                function (Builder $q, $date) {
                                    return $q->whereDate('FechaVencimiento', '<=', $date);
                                }
                            );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Mostrar solo créditos activos vencidos (con fecha vencimiento <= hoy) y con saldo pendiente > 0
                $query->where('Activo', 1)
                    ->whereDate('FechaVencimiento', '<=', \Carbon\Carbon::today())
                    ->whereHas('proposicion', function ($q) {
                        $q->where('SaldoPendiente', '>', 0);
                    })
                    ->with(['proposicion.cliente', 'proposicion.tipoCredito', 'proposicion.zona'])
                    // OPTIMIZACIÓN N+1: Precalcular total_pagado en una sola subquery por página,
                    // en vez de ejecutar Pago::sum() por cada fila en getStateUsing.
                    ->addSelect([
                        'total_pagado' => \App\Models\Pago::selectRaw('COALESCE(SUM(pago.MontoPagado), 0)')
                            ->join('cuota', 'pago.CuotaID', '=', 'cuota.CuotaID')
                            ->whereColumn('cuota.CreditoID', '=', 'Credito.CreditoID')
                            ->where('pago.Activo', 1),
                    ]);
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('FechaVencimiento', 'asc')
            ->paginationPageOptions([10, 25, 50]);
    }

    
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_reporte::creditos::vencidos');
    }
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteCreditosVencidos::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

        return false;
    }

    public static function canEdit($record): bool
    {
        if (!parent::canEdit($record)) { return false; }

        return false;
    }

    public static function canDelete($record): bool
    {
        if (!parent::canDelete($record)) { return false; }

        return false;
    }
}
