<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteCuentasCanceladasResource\Pages;
use App\Models\ProposicionCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class ReporteCuentasCanceladasResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Cuentas Canceladas';
    protected static ?string $pluralLabel = 'Cuentas Canceladas en el Día';

    /**
     * Override para usar los permisos de 'reporte::cuentas::canceladas' explícitamente.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_reporte::cuentas::canceladas') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_reporte::cuentas::canceladas') ?? false;
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
                Tables\Columns\TextColumn::make('operacion')
                    ->label('OPERACION')
                    ->getStateUsing(fn($record) => str_pad($record->ProposicionCreditoID, 11, '0', STR_PAD_LEFT))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('CLIENTE')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('CodigoCredito')
                    ->label('CUENTA')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoTotalPagar')
                    ->label('TOTAL')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('credito.FechaGeneracion')
                    ->label('FECHA')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('credito.FechaVencimiento')
                    ->label('VENCIMIENTO')
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
                    ->relationship('cliente', 'NombresApellidos')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('Fecha')
                    ->label('Fecha de Cancelación')
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
                                    return $q->whereDate('FechaModificacion', '>=', $date);
                                }
                            )
                            ->when(
                                $data['fecha_hasta'] ?? null,
                                function (Builder $q, $date) {
                                    return $q->whereDate('FechaModificacion', '<=', $date);
                                }
                            );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Mostrar solo proposiciones con saldo = 0 (canceladas)
                $query->whereRaw('SaldoPendiente = 0')
                    ->with(['cliente', 'credito'])
                    ->orderByDesc('FechaModificacion');
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteCuentasCanceladas::route('/'),
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
