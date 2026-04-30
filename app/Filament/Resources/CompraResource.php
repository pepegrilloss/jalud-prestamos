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
                            ->searchable(),
                        Forms\Components\TextInput::make('Numero')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->label('Serie / Número')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('FechaEmision')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->label('Fecha Emisión')
                            ->required()
                            ->minDate(now()->startOfMonth())
                            ->maxDate(now()->endOfMonth()),
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
                                    ->step(0.01)
                                    ->default(1)
                                    ->live(debounce: 500),
                                Forms\Components\TextInput::make('PrecioUnitario')
                                    ->label('Precio Unit.')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
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

                        Forms\Components\Grid::make(3)
                            ->schema([
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

        $igv = $subtotalBase * 0.18;
        $set('MontoIGV', number_format($igv, 2, '.', ''));
        $set('Total', number_format($subtotalBase + $igv, 2, '.', ''));
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
                Tables\Columns\IconColumn::make('Activo')
                    ->label('Estado')
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
                    ->action(fn($record) => $record->update(['Activo' => false]))
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
        return parent::canCreate() && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record) && \App\Models\AperturaCierreDia::estaAbierto();
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
