<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\Compra;
use App\Models\TipoComprobante;
use App\Models\Proveedor;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
use Illuminate\Support\Facades\DB;
class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $navigationGroup = 'Compras y Gastos';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 2001;
    protected static ?string $label = 'Compra';
    protected static ?string $pluralLabel = 'Compras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Comprobante')
                    ->schema([
                        Forms\Components\Select::make('TipoComprobanteID')
                            ->prefixIcon('heroicon-m-document-text')
                            ->label('Tipo de Comprobante')
                            ->options(TipoComprobante::where('Activo', true)->pluck('Nombre', 'TipoComprobanteID'))
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('Nombre')
                                    ->label('Nombre del Comprobante')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $data['Activo'] = true;
                                $comprobante = TipoComprobante::create($data);
                                return $comprobante->TipoComprobanteID;
                            }),
                        Forms\Components\TextInput::make('Numero')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->label('Serie / Número')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('FechaEmision')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->label('Fecha Emisión')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Proveedor')
                    ->schema([
                        Forms\Components\Select::make('ProveedorID')
                            ->prefixIcon('heroicon-m-building-storefront')
                            ->label('Proveedor')
                            ->options(Proveedor::where('Activo', true)->pluck('Nombre', 'ProveedorID'))
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('Codigo')
                                    ->label('Código')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('Nombre')
                                    ->label('Nombre / Razón Social')
                                    ->required()
                                    ->maxLength(400),
                                Forms\Components\TextInput::make('RUC')
                                    ->label('RUC')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('Direccion')
                                    ->label('Dirección')
                                    ->required()
                                    ->maxLength(400),
                                Forms\Components\TextInput::make('Telefono')
                                    ->label('Teléfono')
                                    ->maxLength(20),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $proveedor = Proveedor::create($data);
                                return $proveedor->ProveedorID;
                            }),
                    ]),

                Forms\Components\Section::make('Detalle de Compra')
                    ->schema([
                        Forms\Components\Repeater::make('detalles')
                            ->label('Productos / Servicios')
                            ->relationship()
                            ->live(debounce: 500)
                            ->schema([
                                Forms\Components\TextInput::make('ProductoServicio')
                                    ->prefixIcon('heroicon-m-shopping-bag')
                                    ->label('Producto o Servicio')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('Cantidad')
                                    ->prefixIcon('heroicon-m-scale')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->required()
                                    ->step('any')
                                    ->default(1)
                                    ->live(debounce: 500),
                                Forms\Components\TextInput::make('PrecioUnitario')
                                    ->label('Precio Unit.')
                                    ->numeric()
                                    ->required()
                                    ->step('any')
                                    ->prefix('S/. ')
                                    ->live(debounce: 500),
                                Forms\Components\TextInput::make('Subtotal')
                                    ->label('Sub Total')
                                    ->numeric()
                                    ->prefix('S/. ')
                                    ->default(0),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->required()
                            ->minItems(1)
                            ->columnSpanFull()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::calcularTotales($get, $set)),

                        Forms\Components\Grid::make(5)
                            ->schema([
                                Forms\Components\Select::make('TipoIGV')
                                    ->label('Tipo IGV')
                                    ->options(fn () => \App\Models\TipoIgv::where('Activo', true)
                                        ->get()
                                        ->mapWithKeys(fn ($t) => [$t->Codigo => $t->Nombre . ' (' . number_format($t->Porcentaje, 1) . '%)']))
                                    ->default('GRAVADO')
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::calcularTotales($get, $set)),
                                Forms\Components\Select::make('TipoCompra')
                                    ->label('Tipo Compra')
                                    ->options([
                                        'CONTADO' => 'Contado',
                                        'CREDITO' => 'Crédito',
                                    ])
                                    ->default('CONTADO')
                                    ->live(),
                                Forms\Components\TextInput::make('SubtotalBase')
                                    ->label('Subtotal Base')
                                    ->numeric()
                                    ->prefix('S/. ')
                                    ->step(0.01),
                                Forms\Components\TextInput::make('MontoIGV')
                                    ->label('IGV')
                                    ->numeric()
                                    ->prefix('S/. ')
                                    ->step(0.01)
                                    ->hidden(fn (Get $get): bool => $get('TipoIGV') === 'EXONERADO')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $subtotalBase = floatval($get('SubtotalBase') ?? 0);
                                        $igv = floatval($get('MontoIGV') ?? 0);
                                        $set('Total', number_format($subtotalBase + $igv, 2, '.', ''));
                                    }),
                                Forms\Components\TextInput::make('Total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('S/. ')
                                    ->step(0.01),
                            ]),
                    ]),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('Observaciones')
                            ->label('Observaciones')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function calcularTotales(Get $get, Set $set): void
    {
        $detalles = $get('detalles') ?? [];

        foreach ($detalles as $key => $detalle) {
            $cantidad = floatval($detalle['Cantidad'] ?? 0);
            $precio = floatval($detalle['PrecioUnitario'] ?? 0);
            $set("detalles.{$key}.Subtotal", number_format($cantidad * $precio, 2, '.', ''));
        }

        $subtotalBase = collect($detalles)->sum(fn($item) => floatval($item['Cantidad'] ?? 0) * floatval($item['PrecioUnitario'] ?? 0));
        $set('SubtotalBase', number_format($subtotalBase, 2, '.', ''));

        $tipoIGV = \App\Models\TipoIgv::where('Codigo', $get('TipoIGV'))->first();
        $tasa = $tipoIGV?->Porcentaje ?? 0;
        if ($tasa <= 0) {
            $set('MontoIGV', number_format(0, 2, '.', ''));
            $set('Total', number_format($subtotalBase, 2, '.', ''));
        } else {
            $igv = $subtotalBase * ($tasa / 100);
            $set('MontoIGV', number_format($igv, 2, '.', ''));
            $set('Total', number_format($subtotalBase + $igv, 2, '.', ''));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->activos()->with('tipoComprobante', 'proveedor', 'detalles'))
            ->columns([
                Tables\Columns\TextColumn::make('FechaEmision')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoComprobante.Nombre')
                    ->label('Tipo Comprobante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Numero')
                    ->label('Serie / Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('proveedor.Nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('detalles_resumen')
                    ->label('Productos')
                    ->getStateUsing(function ($record) {
                        $nombres = $record->detalles->pluck('ProductoServicio')->toArray();
                        $resumen = implode(', ', $nombres);
                        return strlen($resumen) > 50 ? substr($resumen, 0, 50) . '...' : $resumen;
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('detalles_count')
                    ->label('Ítems')
                    ->getStateUsing(fn($record) => $record->detalles->count())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('Total')
                    ->label('Total')
                    ->numeric(2)
                    ->prefix('S/. ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('TipoIGV')
                    ->label('IGV')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'EXONERADO' ? 'success' : 'warning')
                    ->formatStateUsing(function (string $state): string {
                        $tipo = \App\Models\TipoIgv::where('Codigo', $state)->first();
                        return $tipo ? $tipo->Nombre . ' (' . number_format($tipo->Porcentaje, 1) . '%)' : $state;
                    }),
                Tables\Columns\TextColumn::make('TipoCompra')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'CREDITO' ? 'info' : 'gray')
                    ->formatStateUsing(fn(string $state): string => $state === 'CREDITO' ? 'Crédito' : 'Contado'),
                Tables\Columns\TextColumn::make('EstadoPago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'PENDIENTE' ? 'danger' : 'success')
                    ->formatStateUsing(fn(string $state): string => $state === 'PENDIENTE' ? 'Pendiente' : 'Pagado'),
                Tables\Columns\IconColumn::make('Activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('TipoComprobanteID')
                    ->label('Tipo Comprobante')
                    ->options(TipoComprobante::where('Activo', true)->pluck('Nombre', 'TipoComprobanteID')),
                Tables\Filters\SelectFilter::make('TipoCompra')
                    ->label('Tipo Compra')
                    ->options([
                        'CONTADO' => 'Contado',
                        'CREDITO' => 'Crédito',
                    ]),
                Tables\Filters\SelectFilter::make('EstadoPago')
                    ->label('Estado Pago')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'PAGADO' => 'Pagado',
                    ]),
                Tables\Filters\Filter::make('FechaEmision')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when(
                                $data['fecha_desde'],
                                fn($q) => $q->whereDate('FechaEmision', '>=', $data['fecha_desde'])
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn($q) => $q->whereDate('FechaEmision', '<=', $data['fecha_hasta'])
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->visible(fn($record) => static::canEdit($record)),
                Tables\Actions\Action::make('delete')
                    ->visible(fn($record) => static::canDelete($record))
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Compra')
                    ->modalDescription('¿Está seguro que desea eliminar esta compra?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->action(function ($record) {
                        // Si estaba pagada, revertir Caja Chica
                        if ($record->EstadoPago === 'PAGADO' && (float) $record->Total > 0) {
                            $sedeId = auth()->user()->getEffectiveSedeId();
                            if ($sedeId) {
                                app(\App\Services\FondoSedeService::class)->inyectarCapitalCajaChica(
                                    $sedeId,
                                    (float) $record->Total,
                                    auth()->id(),
                                    "Reversión por eliminación de compra #{$record->CompraID}"
                                );
                            }
                        }
                        $record->update(['Activo' => false]);
                    })
                    ->successNotificationTitle('Compra eliminada correctamente'),
            ])
            ->bulkActions([
            ])
            ->defaultSort('FechaEmision', 'desc')
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) return false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') return true;
        return \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        if (!parent::canEdit($record)) return false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') return true;
        return \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        if (!parent::canDelete($record)) return false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') return true;
        return \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'view' => Pages\ViewCompra::route('/{record}'),
            'edit' => Pages\EditCompra::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
