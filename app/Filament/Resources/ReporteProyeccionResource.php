<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteProyeccionResource\Pages;
use App\Models\CreditoReporteProyeccion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;

class ReporteProyeccionResource extends Resource
{
    protected static ?string $model = CreditoReporteProyeccion::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 5;
    protected static ?string $label = 'Reporte Proyección';
    protected static ?string $pluralLabel = 'Reportes Proyección';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_reporte::proyeccion') ?? false;
    }

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

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Nombres y Apellidos')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto Prestado')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.TasaInteres')
                    ->label('% Interés')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Total (Monto + Interés)')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('Fecha de Giro')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('totalPagado')
                    ->label('Total Pagado')
                    ->money('PEN')
                    ->getStateUsing(fn ($record) => (float) ($record->total_pagado ?? 0))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("(SELECT COALESCE(SUM(p.MontoPagado), 0) FROM pago p WHERE p.CreditoID = Credito.CreditoID AND p.Activo = 1 AND p.EsMora = 0) {$direction}")),

                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaVencimiento')
                    ->label('Fecha de Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.Plazo')
                    ->label('Días (Plazo)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoInteres')
                    ->label('Interés Ganado')
                    ->money('PEN')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(fn () => Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->searchable()
                    ->visible(fn () => auth()->user()?->esAdmin())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q) => $q->where('Credito.SedeID', $data['value'])
                        );
                    }),

                Tables\Filters\Filter::make('rango_fechas')
                    ->label('Rango de Fechas (Vencimiento)')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde')
                            ->native(false)
                            ->live(),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['fecha_desde'] ?? null),
                                fn (Builder $q) => $q->whereDate('Credito.FechaVencimiento', '>=', $data['fecha_desde'])
                            )
                            ->when(
                                filled($data['fecha_hasta'] ?? null),
                                fn (Builder $q) => $q->whereDate('Credito.FechaVencimiento', '<=', $data['fecha_hasta'])
                            );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->where('Credito.Activo', 1);

                // Filtro por sede para no-admins (el scope global no aplica por withoutGlobalScopes en filtros)
                if (!auth()->user()?->esAdmin()) {
                    $query->where('Credito.SedeID', auth()->user()?->getEffectiveSedeId());
                }

                $query->addSelect([
                    'total_pagado' => \App\Models\Pago::selectRaw('COALESCE(SUM(MontoPagado), 0)')
                        ->whereColumn('CreditoID', '=', 'Credito.CreditoID')
                        ->where('Activo', 1)
                        ->where('EsMora', 0),
                ])->with(['proposicion.cliente', 'proposicion.tipoCredito']);
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->defaultSort('Credito.FechaVencimiento', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteProyeccion::route('/'),
        ];
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
}
