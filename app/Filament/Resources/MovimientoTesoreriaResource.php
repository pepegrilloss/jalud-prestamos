<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovimientoTesoreriaResource\Pages;
use App\Models\MovimientoTesoreria;
use App\Services\TesoreriaGerenciaService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovimientoTesoreriaResource extends Resource
{
    protected static ?string $model = MovimientoTesoreria::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Movimientos';

    protected static ?string $modelLabel = 'Movimiento de Tesorería';

    protected static ?string $pluralModelLabel = 'Movimientos de Tesorería';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return self::enGerencia();
    }

    public static function canAccess(): bool
    {
        return self::enGerencia();
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['usuario', 'cuentaOrigen', 'cuentaDestino', 'movimientoOriginal']);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('FechaMovimiento', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('MovimientoTesoreriaID')->label('#')->sortable(),
                Tables\Columns\BadgeColumn::make('Tipo')
                    ->colors([
                        'info' => MovimientoTesoreria::TIPO_APERTURA,
                        'success' => fn (string $state): bool => in_array($state, [
                            MovimientoTesoreria::TIPO_TRANSFERENCIA,
                            MovimientoTesoreria::TIPO_PAGO_PRESTAMO_BANCARIO,
                            MovimientoTesoreria::TIPO_CANCELACION_ANTICIPADA,
                        ], true),
                        'warning' => fn (string $state): bool => in_array($state, [
                            MovimientoTesoreria::TIPO_EXTORNO,
                            MovimientoTesoreria::TIPO_EXTORNO_PAGO_PRESTAMO,
                            MovimientoTesoreria::TIPO_EXTORNO_CANCELACION_ANTICIPADA,
                            MovimientoTesoreria::TIPO_AJUSTE_COMPRA,
                            MovimientoTesoreria::TIPO_AJUSTE_GASTO,
                            MovimientoTesoreria::TIPO_EXTORNO_COMPRA,
                            MovimientoTesoreria::TIPO_EXTORNO_GASTO,
                        ], true),
                        'danger' => fn (string $state): bool => in_array($state, [
                            MovimientoTesoreria::TIPO_EGRESO_COMPRA,
                            MovimientoTesoreria::TIPO_EGRESO_GASTO,
                        ], true),
                    ]),
                Tables\Columns\TextColumn::make('FechaContable')->label('Fecha contable')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('FechaMovimiento')->label('Registrado')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('CuentaOrigenNombre')->label('Origen')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('CuentaDestinoNombre')->label('Destino')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('Monto')->money('PEN')->weight('bold')->sortable(),
                Tables\Columns\TextColumn::make('Concepto')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('usuario.name')->label('Usuario')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Tipo')->options([
                    MovimientoTesoreria::TIPO_APERTURA => 'Apertura',
                    MovimientoTesoreria::TIPO_TRANSFERENCIA => 'Transferencia',
                    MovimientoTesoreria::TIPO_EXTORNO => 'Extorno',
                    MovimientoTesoreria::TIPO_EGRESO_COMPRA => 'Egreso por compra',
                    MovimientoTesoreria::TIPO_AJUSTE_COMPRA => 'Ajuste de compra',
                    MovimientoTesoreria::TIPO_EXTORNO_COMPRA => 'Extorno de compra',
                    MovimientoTesoreria::TIPO_EGRESO_GASTO => 'Egreso por gasto',
                    MovimientoTesoreria::TIPO_AJUSTE_GASTO => 'Ajuste de gasto',
                    MovimientoTesoreria::TIPO_EXTORNO_GASTO => 'Extorno de gasto',
                    MovimientoTesoreria::TIPO_CANCELACION_ANTICIPADA => 'Cancelación anticipada',
                    MovimientoTesoreria::TIPO_EXTORNO_CANCELACION_ANTICIPADA => 'Extorno cancelación anticipada',
                    MovimientoTesoreria::TIPO_PAGO_PRESTAMO_BANCARIO => 'Pago préstamo bancario',
                    MovimientoTesoreria::TIPO_EXTORNO_PAGO_PRESTAMO => 'Extorno pago préstamo',
                ]),
                Tables\Filters\Filter::make('cuenta')
                    ->form([
                        Forms\Components\Select::make('referencia')
                            ->label('Cuenta')
                            ->options(fn () => app(TesoreriaGerenciaService::class)->opcionesCuentas()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $referencia = $data['referencia'] ?? null;
                        if (! $referencia) {
                            return $query;
                        }

                        if ($referencia === TesoreriaGerenciaService::CAJA_GERENCIA_KEY) {
                            return $query->where(function (Builder $movimientos) {
                                $movimientos->where('OrigenTipo', MovimientoTesoreria::CAJA_GERENCIA)
                                    ->orWhere('DestinoTipo', MovimientoTesoreria::CAJA_GERENCIA);
                            });
                        }

                        return $query->where(function (Builder $movimientos) use ($referencia) {
                            $movimientos->where('CuentaOrigenID', $referencia)
                                ->orWhere('CuentaDestinoID', $referencia);
                        });
                    }),
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '<=', $fecha))),
                Tables\Filters\SelectFilter::make('UsuarioID')->label('Usuario')->relationship('usuario', 'name')->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('extornar')
                    ->label('Extornar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (MovimientoTesoreria $record) => $record->Tipo === MovimientoTesoreria::TIPO_TRANSFERENCIA && ! $record->extorno()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Extornar transferencia')
                    ->modalDescription('Se registrará una transferencia inversa. El movimiento original no se modificará.')
                    ->form([
                        Forms\Components\DatePicker::make('FechaContable')->label('Fecha contable')->default(now())->maxDate(now())->required(),
                        Forms\Components\TextInput::make('Concepto')->required()->maxLength(255),
                        Forms\Components\Textarea::make('Observaciones')->maxLength(1000),
                    ])
                    ->action(function (MovimientoTesoreria $record, array $data): void {
                        app(TesoreriaGerenciaService::class)->extornar($record, $data, auth()->id());
                    })
                    ->successNotificationTitle('Extorno registrado correctamente'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Movimiento inalterable')
                ->schema([
                    Infolists\Components\TextEntry::make('Tipo')->badge(),
                    Infolists\Components\TextEntry::make('FechaContable')->label('Fecha contable')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('FechaMovimiento')->label('Registrado')->dateTime('d/m/Y H:i:s'),
                    Infolists\Components\TextEntry::make('CuentaOrigenNombre')->label('Cuenta origen'),
                    Infolists\Components\TextEntry::make('CuentaDestinoNombre')->label('Cuenta destino'),
                    Infolists\Components\TextEntry::make('Monto')->money('PEN'),
                    Infolists\Components\TextEntry::make('Concepto'),
                    Infolists\Components\TextEntry::make('Observaciones')->placeholder('Sin observaciones')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('usuario.name')->label('Usuario'),
                    Infolists\Components\TextEntry::make('MovimientoOriginalID')->label('Movimiento original')->placeholder('No aplica'),
                    Infolists\Components\TextEntry::make('CompraID')->label('Compra')->placeholder('No aplica'),
                    Infolists\Components\TextEntry::make('GastoID')->label('Gasto')->placeholder('No aplica'),
                ])->columns(3),
            Infolists\Components\Section::make('Saldos registrados')
                ->schema([
                    Infolists\Components\TextEntry::make('SaldoAnteriorOrigen')->label('Origen antes')->money('PEN')->placeholder('No aplica'),
                    Infolists\Components\TextEntry::make('SaldoNuevoOrigen')->label('Origen despues')->money('PEN')->placeholder('No aplica'),
                    Infolists\Components\TextEntry::make('SaldoAnteriorDestino')->label('Destino antes')->money('PEN'),
                    Infolists\Components\TextEntry::make('SaldoNuevoDestino')->label('Destino despues')->money('PEN'),
                ])->columns(2),
        ]);
    }

    public static function formularioTransferencia(): array
    {
        return [
            Forms\Components\Select::make('CuentaOrigen')
                ->label('Cuenta origen')
                ->options(fn () => app(TesoreriaGerenciaService::class)->opcionesCuentas())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('CuentaDestino')
                ->label('Cuenta destino')
                ->options(fn () => app(TesoreriaGerenciaService::class)->opcionesCuentas())
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('Monto')->numeric()->prefix('S/')->minValue(0.01)->required(),
            Forms\Components\DatePicker::make('FechaContable')->label('Fecha contable')->default(now())->maxDate(now())->required(),
            Forms\Components\TextInput::make('Concepto')->required()->maxLength(255),
            Forms\Components\Textarea::make('Observaciones')->maxLength(1000)->columnSpanFull(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovimientoTesorerias::route('/'),
            'view' => Pages\ViewMovimientoTesoreria::route('/{record}'),
        ];
    }

    private static function enGerencia(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'gerencia'
            && (auth()->user()?->puedeAccederAGerencia() ?? false);
    }
}
